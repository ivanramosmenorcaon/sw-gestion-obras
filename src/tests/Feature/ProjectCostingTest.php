<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LaborCostRate;
use App\Models\MaterialConsumption;
use App\Models\Party;
use App\Models\PurchaseDeliveryLine;
use App\Models\SalesInvoice;
use App\Models\WorkOrder;
use App\Models\WorkOrderContractorAssignment;
use App\Models\WorkOrderEmployeeAssignment;
use App\Models\WorkOrderPhase;
use App\Models\WorkTimeEntry;
use App\Support\ProjectCosting;
use App\Support\SalesPrice;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProjectCostingTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-04 20:00:00', 'Europe/Madrid'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();
    }

    public function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    private function receivedLine(): PurchaseDeliveryLine
    {
        $line = PurchaseDeliveryLine::factory()->create();
        app(ProjectCosting::class)->receive($line->deliveryNote);

        return $line->refresh();
    }

    public function test_receipt_converts_presentations_and_freezes_purchase_cost_and_fifteen_percent_markup(): void
    {
        $line = PurchaseDeliveryLine::factory()->create(['quantity' => '2', 'unit_price' => '120', 'discount_percentage' => '10']);
        $package = $line->product->productUnits()->create(['code' => 'PACK', 'name' => 'Caja de 12', 'unit_id' => $line->product->base_unit_id, 'base_unit_factor' => '12']);
        $line->update(['product_unit_id' => $package->id]);
        app(ProjectCosting::class)->receive($line->deliveryNote);
        $line->refresh();
        $this->assertSame('24.000000', $line->base_quantity);
        $this->assertSame('9.000000000000', $line->base_unit_cost);
        $this->assertSame('216.00', $line->net_total);
        $this->assertSame('10.350000', $line->suggested_sale_price);
        $this->assertSame('24.000000', app(ProjectCosting::class)->available($line));
        $this->assertSame('received', $line->deliveryNote->status);
        $this->expectException(ValidationException::class);
        $line->update(['unit_price' => '1']);
    }

    public function test_material_drafts_reserve_stock_and_posting_adds_phase_costs_with_exact_return(): void
    {
        $line = $this->receivedLine();
        $phase = WorkOrderPhase::factory()->create();
        $costing = app(ProjectCosting::class);
        $entry = MaterialConsumption::factory()->create(['purchase_delivery_line_id' => $line->id, 'work_order_phase_id' => $phase->id, 'work_order_id' => $phase->work_order_id, 'quantity' => '4']);
        $this->assertSame('6.000000', $costing->available($line));
        $this->assertSame('10.000000', $costing->available($line, false));
        $posted = $costing->postMaterial($entry);
        $this->assertSame('400.00', $posted->total_cost);
        $this->assertSame('460.00', $posted->sale_total);
        $this->assertSame('400.00', $costing->totals($phase)['total']);
        $this->assertSame('400.00', $costing->totals($phase->workOrder)['total']);
        $return = $costing->returnMaterial($posted);
        $this->assertSame('-400.00', $return->total_cost);
        $this->assertSame('10.000000', $costing->available($line));
        $this->assertSame('0.00', $costing->totals($phase)['total']);
        $this->expectException(ValidationException::class);
        $costing->returnMaterial($posted);
    }

    public function test_stock_cannot_be_reserved_twice_and_cancelled_drafts_release_it(): void
    {
        $line = $this->receivedLine();
        $phase = WorkOrderPhase::factory()->create();
        $costing = app(ProjectCosting::class);
        $entry = MaterialConsumption::factory()->create(['purchase_delivery_line_id' => $line->id, 'work_order_phase_id' => $phase->id, 'quantity' => '8']);
        try {
            MaterialConsumption::factory()->create(['purchase_delivery_line_id' => $line->id, 'work_order_phase_id' => $phase->id, 'quantity' => '3']);
            $this->fail('Se permite stock negativo.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }
        $costing->cancel($entry);
        $this->assertSame('10.000000', $costing->available($line));
        $this->assertDatabaseCount('material_consumptions', 1);
    }

    public function test_supplier_and_phase_ownership_are_validated(): void
    {
        $line = $this->receivedLine();
        $phase = WorkOrderPhase::factory()->create();
        $other = WorkOrderPhase::factory()->create();
        $this->expectException(ValidationException::class);
        MaterialConsumption::factory()->create(['purchase_delivery_line_id' => $line->id, 'work_order_phase_id' => $phase->id, 'work_order_id' => $other->work_order_id]);
    }

    public function test_confirmed_cost_plus_purchase_price_has_priority_over_fixed_tariffs_and_product_override_is_optional(): void
    {
        $line = $this->receivedLine();
        $invoice = SalesInvoice::factory()->create(['operation_date' => '2026-11-04']);
        $resolver = app(SalesPrice::class);
        $this->assertSame('115.000000', $resolver->resolve($invoice, $line->product_id));
        $line->product->update(['sales_markup_percentage' => '25']);
        $this->assertSame('125.000000', $resolver->resolve($invoice, $line->product_id));
        $this->assertSame('115.000000', $line->fresh()->suggested_sale_price);
    }

    public function test_worker_time_uses_valid_rate_and_breaks_and_preserves_historical_cost(): void
    {
        $employee = Employee::factory()->create();
        $phase = WorkOrderPhase::factory()->create();
        WorkOrderEmployeeAssignment::factory()->create(['work_order_id' => $phase->work_order_id, 'work_order_phase_id' => $phase->id, 'employee_id' => $employee->id]);
        $rate = LaborCostRate::factory()->create(['employee_id' => $employee->id, 'hourly_cost' => '20']);
        $entry = WorkTimeEntry::factory()->create(['employee_id' => $employee->id, 'work_order_phase_id' => $phase->id, 'started_at' => '2026-11-04 08:00:00', 'ended_at' => '2026-11-04 12:00:00', 'break_minutes' => 30]);
        $posted = app(ProjectCosting::class)->postTime($entry);
        $this->assertSame('3.500000', $posted->hours);
        $this->assertSame('70.00', $posted->total_cost);
        $this->assertSame('70.00', app(ProjectCosting::class)->totals($phase)['labor']);
        $rate->update(['valid_until' => '2026-11-04']);
        LaborCostRate::factory()->create(['employee_id' => $employee->id, 'hourly_cost' => '30', 'valid_from' => '2026-11-05']);
        $this->assertSame('20.000000', $posted->fresh()->hourly_cost);
        $this->expectException(ValidationException::class);
        $posted->update(['break_minutes' => 0]);
    }

    public function test_contractor_hours_are_costed_and_overlapping_rates_are_rejected(): void
    {
        $contractor = Party::factory()->contractor()->create();
        $phase = WorkOrderPhase::factory()->create();
        WorkOrderContractorAssignment::factory()->create(['contractor_id' => $contractor->id, 'work_order_id' => $phase->work_order_id, 'work_order_phase_id' => $phase->id]);
        LaborCostRate::factory()->create(['employee_id' => null, 'contractor_id' => $contractor->id, 'hourly_cost' => '35']);
        $entry = WorkTimeEntry::factory()->create(['employee_id' => null, 'contractor_id' => $contractor->id, 'work_order_phase_id' => $phase->id, 'started_at' => '2026-11-04 08:00:00', 'ended_at' => '2026-11-04 10:00:00']);
        $this->assertSame('70.00', app(ProjectCosting::class)->postTime($entry)->total_cost);
        $this->expectException(ValidationException::class);
        LaborCostRate::factory()->create(['employee_id' => null, 'contractor_id' => $contractor->id, 'valid_from' => '2026-11-04']);
    }

    public function test_hours_require_assignment_and_a_rate_covering_the_entire_interval(): void
    {
        $entry = WorkTimeEntry::factory()->create();
        try {
            app(ProjectCosting::class)->postTime($entry);
            $this->fail('Se permite trabajar sin asignación.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('employee_id', $exception->errors());
        }
        WorkOrderEmployeeAssignment::factory()->create(['employee_id' => $entry->employee_id, 'work_order_id' => $entry->work_order_id]);
        try {
            app(ProjectCosting::class)->postTime($entry);
            $this->fail('Se permite trabajar sin tarifa.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('started_at', $exception->errors());
        }
        $this->assertSame('draft', $entry->fresh()->status);
    }

    public function test_orders_require_a_phase_before_starting_and_cannot_lose_their_last_phase(): void
    {
        $order = WorkOrder::factory()->create();
        try {
            $order->update(['status' => 'in_progress']);
            $this->fail('Se permite una orden activa sin fases.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
        $phase = WorkOrderPhase::factory()->create(['work_order_id' => $order->id]);
        $phase->load('workOrder');
        $order->refresh()->update(['status' => 'in_progress']);
        try {
            $phase->delete();
            $this->fail('Se permite retirar la última fase.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('id', $exception->errors());
        }
        $this->assertDatabaseHas('work_order_phases', ['id' => $phase->id]);
    }

    public function test_confirmed_receipts_and_consumptions_cannot_be_changed_from_stale_instances(): void
    {
        $line = PurchaseDeliveryLine::factory()->create();
        $staleNote = $line->deliveryNote;
        app(ProjectCosting::class)->receive($staleNote);
        try {
            $staleNote->update(['notes' => 'Cambio tardío']);
            $this->fail('Se permite cambiar el albarán confirmado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
        try {
            $line->update(['quantity' => '20']);
            $this->fail('Se permite cambiar una línea recibida.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
        $entry = MaterialConsumption::factory()->create(['purchase_delivery_line_id' => $line->id]);
        app(ProjectCosting::class)->postMaterial($entry);
        $this->expectException(ValidationException::class);
        $entry->update(['quantity' => '2']);
    }

    public function test_overlapping_confirmed_jobs_are_rejected_for_the_same_worker(): void
    {
        $employee = Employee::factory()->create();
        $phase = WorkOrderPhase::factory()->create();
        WorkOrderEmployeeAssignment::factory()->create(['employee_id' => $employee->id, 'work_order_id' => $phase->work_order_id]);
        LaborCostRate::factory()->create(['employee_id' => $employee->id]);
        $first = WorkTimeEntry::factory()->create(['employee_id' => $employee->id, 'work_order_phase_id' => $phase->id, 'started_at' => '2026-11-04 08:00:00', 'ended_at' => '2026-11-04 10:00:00']);
        app(ProjectCosting::class)->postTime($first);
        $overlap = WorkTimeEntry::factory()->create(['employee_id' => $employee->id, 'work_order_phase_id' => $phase->id, 'started_at' => '2026-11-04 09:00:00', 'ended_at' => '2026-11-04 11:00:00']);
        $this->expectException(ValidationException::class);
        app(ProjectCosting::class)->postTime($overlap);
    }

    public function test_a_job_crossing_a_rate_change_must_be_split_and_used_rates_cannot_be_repriced(): void
    {
        $this->travelTo(Carbon::parse('2026-11-06 20:00:00', 'Europe/Madrid'));
        $employee = Employee::factory()->create();
        $phase = WorkOrderPhase::factory()->create();
        WorkOrderEmployeeAssignment::factory()->create(['employee_id' => $employee->id, 'work_order_id' => $phase->work_order_id]);
        $rate = LaborCostRate::factory()->create(['employee_id' => $employee->id, 'valid_until' => '2026-11-04']);
        LaborCostRate::factory()->create(['employee_id' => $employee->id, 'valid_from' => '2026-11-05']);
        $crossing = WorkTimeEntry::factory()->create(['employee_id' => $employee->id, 'work_order_phase_id' => $phase->id, 'started_at' => '2026-11-04 22:30:00', 'ended_at' => '2026-11-04 23:30:00']);
        try {
            app(ProjectCosting::class)->postTime($crossing);
            $this->fail('Se permite un parte con dos tarifas.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('started_at', $exception->errors());
        }
        $entry = WorkTimeEntry::factory()->create(['employee_id' => $employee->id, 'work_order_phase_id' => $phase->id, 'started_at' => '2026-11-04 08:00:00', 'ended_at' => '2026-11-04 09:00:00']);
        app(ProjectCosting::class)->postTime($entry);
        $this->expectException(ValidationException::class);
        $rate->update(['hourly_cost' => '40']);
    }

    public function test_amount_overflow_rolls_back_the_entire_receipt(): void
    {
        $line = PurchaseDeliveryLine::factory()->create(['quantity' => '999999999999', 'unit_price' => '999999999999']);
        try {
            app(ProjectCosting::class)->receive($line->deliveryNote);
            $this->fail('Se permite un importe fuera de rango.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('unit_price', $exception->errors());
        }
        $this->assertSame('draft', $line->deliveryNote->fresh()->status);
        $this->assertNull($line->fresh()->base_unit_cost);
    }

    public function test_products_with_received_material_cannot_change_base_unit_or_become_services(): void
    {
        $line = $this->receivedLine();
        $this->expectException(ValidationException::class);
        $line->product->update(['type' => 'service']);
    }

    public function test_a_cost_rate_cannot_belong_to_both_employee_and_contractor(): void
    {
        $employee = Employee::factory()->create();
        $contractor = Party::factory()->contractor()->create();
        $this->expectException(ValidationException::class);
        LaborCostRate::factory()->create(['employee_id' => $employee->id, 'contractor_id' => $contractor->id]);
    }

    public function test_partial_returns_refund_only_their_proportion_and_final_return_closes_the_cost(): void
    {
        $line = $this->receivedLine();
        $entry = MaterialConsumption::factory()->create(['purchase_delivery_line_id' => $line->id, 'quantity' => '2']);
        $costing = app(ProjectCosting::class);
        $entry = $costing->postMaterial($entry);
        $partial = $costing->returnMaterial($entry, '0.5');
        $this->assertSame('-50.00', $partial->total_cost);
        $this->assertSame('8.500000', $costing->available($line, false));
        $last = $costing->returnMaterial($entry);
        $this->assertSame('-150.00', $last->total_cost);
        $this->assertSame('10.000000', $costing->available($line, false));
        $this->expectException(ValidationException::class);
        $costing->returnMaterial($entry, '0.1');
    }

    public function test_hour_cost_is_calculated_from_elapsed_seconds_before_rounding_display_hours(): void
    {
        $employee = Employee::factory()->create();
        $phase = WorkOrderPhase::factory()->create();
        WorkOrderEmployeeAssignment::factory()->create(['employee_id' => $employee->id, 'work_order_id' => $phase->work_order_id]);
        LaborCostRate::factory()->create(['employee_id' => $employee->id, 'hourly_cost' => '60000']);
        $entry = WorkTimeEntry::factory()->create(['employee_id' => $employee->id, 'work_order_phase_id' => $phase->id, 'started_at' => '2026-11-04 08:00:00', 'ended_at' => '2026-11-04 08:01:00']);
        $this->assertSame('1000.00', app(ProjectCosting::class)->postTime($entry)->total_cost);
    }

    public function test_markup_is_applied_before_rounding_small_base_unit_costs(): void
    {
        $line = PurchaseDeliveryLine::factory()->create(['quantity' => '1', 'unit_price' => '49']);
        $package = $line->product->productUnits()->create(['code' => 'GRANEL', 'name' => 'Granel', 'unit_id' => $line->product->base_unit_id, 'base_unit_factor' => '100000000']);
        $line->update(['product_unit_id' => $package->id]);
        app(ProjectCosting::class)->receive($line->deliveryNote);
        $this->assertSame('0.000001', app(ProjectCosting::class)->productQuote($line->product_id, '2026-11-04')['sale']);
    }
}
