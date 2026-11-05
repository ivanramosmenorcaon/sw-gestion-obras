<?php

namespace Tests\Feature;

use App\Filament\Resources\Budgets\Pages\EditBudget;
use App\Filament\Resources\Budgets\Pages\ListBudgets;
use App\Filament\Resources\PurchaseDeliveryNotes\Pages\EditPurchaseDeliveryNote;
use App\Models\Budget;
use App\Models\Product;
use App\Models\PurchaseDeliveryNote;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderLine;
use App\Models\WorkOrderPhase;
use App\Models\WorkTimeEntry;
use App\Support\BudgetEditor;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KeyboardDocumentsTest extends TestCase
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
    }

    public function test_empty_budget_can_be_created_and_manual_lines_saved_without_measurements(): void
    {
        $this->actingAs(User::factory()->create());
        $order = WorkOrder::factory()->create();
        Livewire::test(ListBudgets::class)->callAction('newBudget', [
            'source_work_order_id' => $order->id, 'name' => 'Oferta manual',
        ])->assertHasNoActionErrors();
        $budget = Budget::sole();
        $this->assertSame([], $budget->items);
        Livewire::test(EditBudget::class, ['record' => $budget->id])
            ->call('addManualLine')
            ->set('manualLines.0.description', 'Trabajo y detalles')
            ->set('manualLines.0.quantity', '2')
            ->set('manualLines.0.price', '12.50')
            ->callAction('saveBudget')->assertHasNoActionErrors()->assertNotified();
        $this->assertSame('25.00', $budget->fresh()->total());
        $this->assertSame(0, $order->workOrderLines()->count());
        $this->get(route('documents.print', ['type' => 'budget', 'id' => $budget->id]))
            ->assertOk()->assertSee('Trabajo y detalles')->assertSee('25,00');
    }

    public function test_manual_lines_can_be_removed_including_the_last_line(): void
    {
        $this->actingAs(User::factory()->create());
        $budget = Budget::factory()->create(['items' => []]);
        app(BudgetEditor::class)->saveManualLines($budget, [
            ['description' => 'Primera', 'unit' => 'ud', 'quantity' => '1', 'price' => '5'],
            ['description' => 'Segunda', 'unit' => 'ud', 'quantity' => '2', 'price' => '10'],
        ]);

        $page = Livewire::test(EditBudget::class, ['record' => $budget->id])
            ->assertSee('Eliminar línea')->call('removeManualLine', 0)
            ->assertSet('manualLines.0.description', 'Segunda')
            ->callAction('saveBudget')->assertHasNoActionErrors();
        $this->assertCount(1, $budget->fresh()->items);
        $this->assertSame('20.00', $budget->fresh()->total());

        $page->call('removeManualLine', 0)->callAction('saveBudget')->assertHasNoActionErrors();
        $this->assertSame([], $budget->fresh()->items);
        $this->assertSame('0.00', $budget->fresh()->total());
    }

    public function test_last_copied_line_can_be_removed_without_deleting_the_measurement(): void
    {
        $this->actingAs(User::factory()->create());
        $line = WorkOrderLine::factory()->create();
        $budget = app(BudgetEditor::class)->create($line->workOrder, 'Oferta', [
            $line->id => ['included' => true, 'quantity' => '1', 'price' => '5'],
        ]);

        Livewire::test(EditBudget::class, ['record' => $budget->id])
            ->call('removeBudgetLine', $line->id)
            ->callAction('saveBudget')->assertHasNoActionErrors()->assertNotified('Presupuesto guardado');

        $this->assertSame([], $budget->fresh()->items);
        $this->assertModelExists($line);
    }

    public function test_manual_line_validation_rejects_negative_prices(): void
    {
        $this->actingAs(User::factory()->create());
        $budget = Budget::factory()->create();
        Livewire::test(EditBudget::class, ['record' => $budget->id])
            ->call('addManualLine')->set('manualLines.0.description', 'Trabajo')
            ->set('manualLines.0.price', '-1')->callAction('saveBudget')
            ->assertHasErrors('lines.0.price');
    }

    public function test_delivery_lines_can_be_saved_together_in_the_keyboard_form(): void
    {
        $this->actingAs(User::factory()->create());
        $note = PurchaseDeliveryNote::factory()->create();
        $product = Product::factory()->create(['tracks_stock' => true]);
        Livewire::test(EditPurchaseDeliveryNote::class, ['record' => $note->id])
            ->fillForm(['lines' => [
                ['product_id' => $product->id, 'description' => 'Primera', 'position' => 1, 'quantity' => '2', 'unit_price' => '3', 'discount_percentage' => '0'],
                ['product_id' => $product->id, 'description' => 'Segunda', 'position' => 2, 'quantity' => '4', 'unit_price' => '5', 'discount_percentage' => '0'],
            ]])->call('save')->assertHasNoFormErrors();
        $this->assertSame(2, $note->lines()->count());
        $this->get(route('documents.print', ['type' => 'delivery', 'id' => $note->id]))->assertOk()->assertSee('Primera')->assertSee('Segunda');
    }

    public function test_orders_phases_and_time_entries_have_printable_documents(): void
    {
        $this->actingAs(User::factory()->create());
        foreach ([
            'order' => WorkOrder::factory()->create(),
            'phase' => WorkOrderPhase::factory()->create(),
            'time' => WorkTimeEntry::factory()->create(),
        ] as $type => $record) {
            $this->get(route('documents.print', ['type' => $type, 'id' => $record->id]))
                ->assertOk()->assertSee('Imprimir / Guardar PDF');
        }
    }

    public function test_printing_requires_authentication_and_unknown_types_are_not_available(): void
    {
        $budget = Budget::factory()->create();
        $this->get(route('documents.print', ['type' => 'budget', 'id' => $budget->id]))->assertRedirect();
        $this->actingAs(User::factory()->create());
        $this->get(route('documents.print', ['type' => 'invalid', 'id' => $budget->id]))->assertNotFound();
    }
}
