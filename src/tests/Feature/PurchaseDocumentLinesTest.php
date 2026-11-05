<?php

namespace Tests\Feature;

use App\Filament\Resources\PurchaseInvoices\Pages\ManagePurchaseInvoices;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use App\Support\OperationsManager;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PurchaseDocumentLinesTest extends TestCase
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
        $this->actingAs(User::factory()->create());
    }

    public function test_purchase_invoice_totals_are_derived_from_lines_and_recalculate_after_deletion(): void
    {
        $invoice = PurchaseInvoice::factory()->create();
        $line = PurchaseInvoiceLine::factory()->create(['purchase_invoice_id' => $invoice->id, 'quantity' => '2', 'unit_price' => '100', 'discount_percentage' => '10', 'tax_percentage' => '21', 'withholding_percentage' => '15', 'retention_percentage' => '5']);
        $this->assertSame('180.00', $line->net_amount);
        $this->assertSame('181.80', $invoice->fresh()->payable_total);
        $line->update(['quantity' => '3']);
        $this->assertSame('272.70', $invoice->fresh()->payable_total);
        $line->delete();
        $this->assertSame('0.00', $invoice->fresh()->net_amount);
    }

    public function test_order_lines_calculate_amount_and_are_immutable_after_confirmation(): void
    {
        $order = PurchaseOrder::factory()->create();
        $line = PurchaseOrderLine::factory()->create(['purchase_order_id' => $order->id, 'quantity' => '2', 'unit_price' => '10', 'discount_percentage' => '5']);
        $this->assertSame('19.00', $order->fresh()->amount);
        app(OperationsManager::class)->post($order);
        $this->expectException(ValidationException::class);
        $line->update(['unit_price' => '1']);
    }

    public function test_purchase_invoice_lines_can_be_saved_from_the_form(): void
    {
        $invoice = PurchaseInvoice::factory()->create();
        $page = Livewire::test(ManagePurchaseInvoices::class)->mountAction(TestAction::make('edit')->table($invoice));
        $page->fillForm([
            'project_id' => $invoice->project_id, 'supplier_id' => $invoice->supplier_id,
            'reference' => $invoice->reference, 'description' => 'Compra con detalle',
            'operation_date' => today()->toDateString(), 'due_date' => today()->addDays(30)->toDateString(),
            'lines' => [[
                'description' => 'Cajas de material', 'unit_label' => 'caja', 'quantity' => '3',
                'unit_price' => '10', 'discount_percentage' => '0', 'tax_percentage' => '21',
                'withholding_percentage' => '0', 'retention_percentage' => '0',
            ]],
        ], $page->instance()->getMountedActionSchemaName())->callMountedAction()->assertHasNoActionErrors();
        $this->assertCount(1, $invoice->fresh()->lines);
        $this->assertSame('36.30', $invoice->fresh()->payable_total);
    }
}
