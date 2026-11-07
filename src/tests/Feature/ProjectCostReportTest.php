<?php

namespace Tests\Feature;

use App\Filament\Resources\Projects\Pages\ProjectCosts;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Projects\Widgets\ProjectCostBreakdownChart;
use App\Filament\Resources\Projects\Widgets\ProjectCostHistoryChart;
use App\Filament\Resources\Projects\Widgets\ProjectCostStats;
use App\Models\ContractorAgreement;
use App\Models\ContractorCertificate;
use App\Models\LaborCostRate;
use App\Models\MaterialConsumption;
use App\Models\Payment;
use App\Models\PaymentReversal;
use App\Models\Project;
use App\Models\ProjectExpense;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceLine;
use App\Models\User;
use App\Models\WorkOrderEmployeeAssignment;
use App\Models\WorkTimeEntry;
use App\Models\WorkTimeReversal;
use App\Support\OperationsManager;
use App\Support\ProjectCosting;
use App\Support\ProjectCostReport;
use App\Support\ProjectEconomics;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ProjectCostReportTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();
        $this->travelTo(Carbon::parse('2026-11-07 12:00:00', 'Europe/Madrid'));
    }

    public function test_monthly_costs_preserve_cents_fill_empty_months_and_include_the_opening_balance(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();
        foreach (['2025-04-01' => '10.01', '2026-10-15' => '20.02', '2026-11-01' => '30.03'] as $date => $amount) {
            app(OperationsManager::class)->post(ProjectExpense::factory()->create(['project_id' => $project->id, 'operation_date' => $date, 'amount' => $amount]));
        }
        ProjectExpense::factory()->create(['project_id' => $project->id, 'amount' => '900']);
        app(OperationsManager::class)->post(ProjectExpense::factory()->create(['amount' => '800']));

        $history = app(ProjectCostReport::class)->history($project, 6);

        $this->assertSame(['06/2026', '07/2026', '08/2026', '09/2026', '10/2026', '11/2026'], $history['labels']);
        $this->assertSame(['0.00', '0.00', '0.00', '0.00', '20.02', '30.03'], $history['monthly']);
        $this->assertSame(['10.01', '10.01', '10.01', '10.01', '30.03', '60.06'], $history['cumulative']);
        $this->assertSame('10.01', $history['opening']);
        $this->assertSame('60.06', app(ProjectEconomics::class)->forProject($project)['actual']);
    }

    public function test_empty_project_has_zero_costs_and_invalid_interval_falls_back_to_twelve_months(): void
    {
        $project = Project::factory()->create();

        $history = app(ProjectCostReport::class)->history($project, 999999);

        $this->assertCount(12, $history['labels']);
        $this->assertSame(array_fill(0, 12, '0.00'), $history['monthly']);
        $this->assertSame(array_fill(0, 12, '0.00'), $history['cumulative']);
        $this->assertSame('0.00', app(ProjectEconomics::class)->forProject($project)['actual']);
    }

    public function test_material_returns_and_linked_invoices_do_not_double_the_real_cost(): void
    {
        $entry = MaterialConsumption::factory()->create(['quantity' => '2']);
        $costing = app(ProjectCosting::class);
        $costing->postMaterial($entry);
        $costing->returnMaterial($entry, '1');
        $project = $entry->workOrder->project;
        $note = $entry->purchaseDeliveryLine->deliveryNote;
        PurchaseInvoice::factory()->confirmed()->create(['project_id' => $project->id, 'supplier_id' => $note->supplier_id, 'purchase_delivery_note_id' => $note->id, 'net_amount' => '1000']);
        PurchaseInvoice::factory()->confirmed()->create(['project_id' => $project->id, 'net_amount' => '50.25']);

        $totals = app(ProjectEconomics::class)->forProject($project);
        $history = app(ProjectCostReport::class)->history($project, 6);

        $this->assertSame('100.00', $totals['materials']);
        $this->assertSame('50.25', $totals['direct_purchases']);
        $this->assertSame('150.25', $totals['actual']);
        $this->assertSame('150.25', $history['cumulative'][5]);
    }

    public function test_confirmed_time_reversal_removes_cost_from_its_original_month(): void
    {
        $this->actingAs(User::factory()->create());
        $entry = WorkTimeEntry::factory()->create();
        WorkOrderEmployeeAssignment::factory()->create(['employee_id' => $entry->employee_id, 'work_order_id' => $entry->work_order_id]);
        LaborCostRate::factory()->create(['employee_id' => $entry->employee_id, 'hourly_cost' => '20']);
        app(ProjectCosting::class)->postTime($entry);
        app(OperationsManager::class)->post(WorkTimeReversal::factory()->create(['work_time_entry_id' => $entry->id]));

        $history = app(ProjectCostReport::class)->history($entry->workOrder->project, 6);

        $this->assertSame(array_fill(0, 6, '0.00'), $history['cumulative']);
        $this->assertSame('0.00', app(ProjectEconomics::class)->forProject($entry->workOrder->project)['labor']);
    }

    public function test_mixed_invoice_counts_only_independent_lines_as_direct_cost(): void
    {
        $this->actingAs(User::factory()->create());
        $entry = MaterialConsumption::factory()->create(['quantity' => '2']);
        app(ProjectCosting::class)->postMaterial($entry);
        $project = $entry->workOrder->project;
        $deliveryLine = $entry->purchaseDeliveryLine;
        $invoice = PurchaseInvoice::factory()->create(['project_id' => $project->id, 'supplier_id' => $deliveryLine->deliveryNote->supplier_id]);
        PurchaseInvoiceLine::factory()->create(['purchase_invoice_id' => $invoice->id, 'purchase_delivery_line_id' => $deliveryLine->id, 'product_id' => $deliveryLine->product_id, 'quantity' => '2', 'unit_price' => '100']);
        PurchaseInvoiceLine::factory()->create(['purchase_invoice_id' => $invoice->id, 'description' => 'Transporte independiente', 'quantity' => '1', 'unit_price' => '25.50']);
        app(OperationsManager::class)->post($invoice->refresh());

        $totals = app(ProjectEconomics::class)->forProject($project);

        $this->assertSame('225.50', $invoice->fresh()->net_amount);
        $this->assertSame('200.00', $totals['materials']);
        $this->assertSame('25.50', $totals['direct_purchases']);
        $this->assertSame('225.50', $totals['actual']);
        $this->assertSame('225.50', app(ProjectCostReport::class)->history($project, 6)['cumulative'][5]);
    }

    public function test_certified_subcontracting_is_counted_once_and_cash_is_separate_from_cost(): void
    {
        $this->actingAs(User::factory()->create());
        $agreement = ContractorAgreement::factory()->confirmed()->create(['amount' => '1000']);
        $project = $agreement->project;
        app(OperationsManager::class)->post(ContractorCertificate::factory()->create(['contractor_agreement_id' => $agreement->id, 'project_id' => $project->id, 'amount' => '250']));
        PurchaseInvoice::factory()->confirmed()->create(['project_id' => $project->id, 'supplier_id' => $agreement->contractor_id, 'contractor_agreement_id' => $agreement->id, 'net_amount' => '250']);
        $payment = Payment::factory()->create(['project_id' => $project->id, 'party_id' => $agreement->contractor_id, 'direction' => 'payment', 'amount' => '200']);
        app(OperationsManager::class)->post($payment);
        app(OperationsManager::class)->post(PaymentReversal::factory()->create(['project_id' => $project->id, 'payment_id' => $payment->id, 'amount' => '50']));

        $totals = app(ProjectEconomics::class)->forProject($project);

        $this->assertSame('250.00', $totals['actual']);
        $this->assertSame('1000.00', $totals['committed']);
        $this->assertSame('150.00', $totals['paid']);
        $this->assertSame('-150.00', $totals['cash']);
        $this->assertSame('250.00', app(ProjectCostReport::class)->history($project, 6)['cumulative'][5]);
    }

    public function test_authorized_project_page_and_widgets_display_the_same_real_expense(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create(['name' => 'Reforma del local']);
        app(OperationsManager::class)->post(ProjectExpense::factory()->create(['project_id' => $project->id, 'amount' => '123.45']));

        $this->get(ProjectResource::getUrl('costs', ['record' => $project]))->assertOk()->assertSee('Reforma del local')->assertSee('123,45');
        Livewire::test(ProjectCostStats::class, ['projectId' => $project->id])->assertSuccessful()->assertSee('123,45')->assertSee('Coste real incurrido');
        Livewire::test(ProjectCostBreakdownChart::class, ['projectId' => $project->id])->assertSuccessful()->assertSee('Gastos de obra: 123,45');
        Livewire::test(ProjectCostHistoryChart::class, ['projectId' => $project->id])->set('filter', '6')->assertSuccessful()->assertSee('11/2026: mensual 123,45');
    }

    #[TestWith(['worker'])]
    #[TestWith(['foreman'])]
    public function test_roles_without_cost_access_cannot_open_the_page_or_widgets(string $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]));
        $project = Project::factory()->create();

        $this->get(ProjectResource::getUrl('costs', ['record' => $project]))->assertNotFound();
        Livewire::test(ProjectCostStats::class, ['projectId' => $project->id])->assertForbidden();
        Livewire::test(ProjectCostBreakdownChart::class, ['projectId' => $project->id])->assertForbidden();
        Livewire::test(ProjectCostHistoryChart::class, ['projectId' => $project->id])->assertForbidden();
    }

    public function test_manager_access_and_open_components_are_revoked_when_project_changes_owner(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $project = Project::factory()->create(['manager_id' => $manager->id]);
        $other = Project::factory()->create();
        $administrator = User::factory()->create();
        $this->actingAs($manager);
        $page = Livewire::test(ProjectCosts::class, ['record' => $project->id])->assertSuccessful();
        $widget = Livewire::test(ProjectCostHistoryChart::class, ['projectId' => $project->id])->assertSuccessful();
        $this->get(ProjectResource::getUrl('costs', ['record' => $other]))->assertNotFound();

        $this->actingAs($administrator);
        $project->update(['manager_id' => null]);
        $this->actingAs($manager);

        $page->call('$refresh')->assertNotFound();
        $widget->set('filter', '6')->assertNotFound();
    }

    public function test_guest_is_redirected_to_login_and_inactive_account_cannot_read_widgets(): void
    {
        $project = Project::factory()->create();

        $this->get(ProjectResource::getUrl('costs', ['record' => $project]))->assertRedirect(Filament::getLoginUrl());
        $this->actingAs(User::factory()->create(['is_active' => false]));
        Livewire::test(ProjectCostStats::class, ['projectId' => $project->id])->assertForbidden();
    }
}
