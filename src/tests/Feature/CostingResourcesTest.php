<?php

namespace Tests\Feature;

use App\Filament\Resources\LaborCostRates\LaborCostRateResource;
use App\Filament\Resources\LaborCostRates\Pages\CreateLaborCostRate;
use App\Filament\Resources\MaterialConsumptions\MaterialConsumptionResource;
use App\Filament\Resources\MaterialConsumptions\Pages\CreateMaterialConsumption;
use App\Filament\Resources\MaterialConsumptions\Pages\EditMaterialConsumption;
use App\Filament\Resources\PurchaseDeliveryNotes\Pages\EditPurchaseDeliveryNote;
use App\Filament\Resources\PurchaseDeliveryNotes\Pages\ViewPurchaseDeliveryNote;
use App\Filament\Resources\PurchaseDeliveryNotes\PurchaseDeliveryNoteResource;
use App\Filament\Resources\PurchaseDeliveryNotes\RelationManagers\LinesRelationManager;
use App\Filament\Resources\Warehouses\WarehouseResource;
use App\Filament\Resources\WorkOrderPhases\Pages\EditWorkOrderPhase;
use App\Filament\Resources\WorkOrderPhases\RelationManagers\MaterialConsumptionsRelationManager;
use App\Filament\Resources\WorkTimeEntries\Pages\CreateWorkTimeEntry;
use App\Filament\Resources\WorkTimeEntries\Pages\EditWorkTimeEntry;
use App\Filament\Resources\WorkTimeEntries\WorkTimeEntryResource;
use App\Models\Employee;
use App\Models\MaterialConsumption;
use App\Models\Product;
use App\Models\PurchaseDeliveryLine;
use App\Models\PurchaseDeliveryNote;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkOrderEmployeeAssignment;
use App\Models\WorkOrderPhase;
use App\Models\WorkTimeEntry;
use App\Support\ProjectCosting;
use Carbon\Carbon;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class CostingResourcesTest extends TestCase
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

    public function test_new_resources_render_creation_and_listing_pages_in_spanish(): void
    {
        $this->actingAs(User::factory()->create());
        foreach ([WarehouseResource::class, PurchaseDeliveryNoteResource::class, MaterialConsumptionResource::class, LaborCostRateResource::class, WorkTimeEntryResource::class] as $resource) {
            Livewire::test($resource::getPages()['index']->getPage())->assertSuccessful();
            Livewire::test($resource::getPages()['create']->getPage())->assertSuccessful();
        }
    }

    public function test_receipt_lines_can_be_created_and_received_from_filament(): void
    {
        $this->actingAs(User::factory()->create());
        $note = PurchaseDeliveryNote::factory()->create();
        $product = Product::factory()->create(['tracks_stock' => true]);
        Livewire::test(LinesRelationManager::class, ['ownerRecord' => $note, 'pageClass' => EditPurchaseDeliveryNote::class])->callAction(TestAction::make('create')->table(), ['product_id' => $product->id, 'description' => 'Material para instalación', 'position' => 1, 'quantity' => '10', 'unit_price' => '5', 'discount_percentage' => '0'])->assertHasNoFormErrors();
        Livewire::test(EditPurchaseDeliveryNote::class, ['record' => $note->id])->callAction('confirm')->assertHasNoActionErrors()->assertRedirect();
        $this->assertSame('received', $note->fresh()->status);
        $this->assertSame('5.750000', $note->lines()->first()->suggested_sale_price);
        Livewire::test(ViewPurchaseDeliveryNote::class, ['record' => $note->id])->assertSuccessful();
        Livewire::test(EditPurchaseDeliveryNote::class, ['record' => $note->id])->assertForbidden();
    }

    public function test_receipt_editor_keeps_lines_created_by_its_relation_manager_after_the_page_was_opened(): void
    {
        $this->actingAs(User::factory()->create());
        $note = PurchaseDeliveryNote::factory()->create();
        $product = Product::factory()->create(['tracks_stock' => true]);
        $page = Livewire::test(EditPurchaseDeliveryNote::class, ['record' => $note->id]);
        Livewire::test(LinesRelationManager::class, ['ownerRecord' => $note, 'pageClass' => EditPurchaseDeliveryNote::class])
            ->callAction(TestAction::make('create')->table(), ['product_id' => $product->id, 'description' => 'Material desde la relación', 'position' => 1, 'quantity' => '2', 'unit_price' => '5', 'discount_percentage' => '0'])
            ->assertHasNoActionErrors()->assertDispatched('purchase-delivery-lines-changed', noteId: $note->id);
        $page->dispatch('purchase-delivery-lines-changed', noteId: $note->id)
            ->callAction('confirm')->assertHasNoActionErrors()->assertRedirect();
        $this->assertSame('received', $note->fresh()->status);
        $this->assertSame(1, $note->lines()->count());
        $this->assertSame('10.00', $note->fresh()->net_total);
    }

    public function test_stale_receipt_form_cannot_delete_a_line_saved_in_another_component(): void
    {
        $this->actingAs(User::factory()->create());
        $note = PurchaseDeliveryNote::factory()->create();
        $page = Livewire::test(EditPurchaseDeliveryNote::class, ['record' => $note->id]);
        $line = PurchaseDeliveryLine::factory()->create(['purchase_delivery_note_id' => $note->id]);

        $page->callAction('confirm')->assertHasErrors('data.lines');

        $this->assertModelExists($line);
        $this->assertSame('draft', $note->fresh()->status);
        $this->assertSame(1, $note->lines()->count());
    }

    public function test_relation_updates_do_not_discard_unsaved_lines_from_the_parent_form(): void
    {
        $this->actingAs(User::factory()->create());
        $note = PurchaseDeliveryNote::factory()->create();
        $product = Product::factory()->create(['tracks_stock' => true]);
        $page = Livewire::test(EditPurchaseDeliveryNote::class, ['record' => $note->id])
            ->set('data.lines.unsaved', ['product_id' => $product->id, 'description' => 'Borrador que se conserva', 'position' => 1, 'quantity' => '2', 'unit_price' => '3', 'discount_percentage' => '0']);
        $line = PurchaseDeliveryLine::factory()->create(['purchase_delivery_note_id' => $note->id]);

        $page->dispatch('purchase-delivery-lines-changed', noteId: $note->id)
            ->assertSet('data.lines.unsaved.description', 'Borrador que se conserva')
            ->callAction('confirm')->assertHasErrors('data.lines');

        $this->assertModelExists($line);
    }

    public function test_failed_reception_rolls_back_edits_to_previously_saved_lines(): void
    {
        $this->actingAs(User::factory()->create());
        $line = PurchaseDeliveryLine::factory()->create(['unit_price' => '5']);
        $note = $line->deliveryNote;
        $page = Livewire::test(EditPurchaseDeliveryNote::class, ['record' => $note->id])
            ->set('data.lines.record-'.$line->id.'.unit_price', '9')
            ->set('data.received_date', today('Europe/Madrid')->addDay()->toDateString());

        $page->callAction('confirm')->assertHasErrors('received_date');

        $this->assertSame('5.000000', $line->fresh()->unit_price);
        $this->assertSame('draft', $note->fresh()->status);
        $page->call('unmountAction')->set('data.received_date', today('Europe/Madrid')->toDateString())->callAction('confirm')->assertHasNoActionErrors()->assertRedirect();
        $this->assertSame('9.000000', $line->fresh()->unit_price);
        $this->assertSame('received', $note->fresh()->status);
    }

    public function test_phase_material_relation_manager_fixes_phase_and_order_in_server(): void
    {
        $this->actingAs(User::factory()->create());
        $line = PurchaseDeliveryLine::factory()->create();
        app(ProjectCosting::class)->receive($line->deliveryNote);
        $phase = WorkOrderPhase::factory()->create();
        Livewire::test(EditWorkOrderPhase::class, ['record' => $phase->id])->assertSee(__('costing.costs'));
        Livewire::test(MaterialConsumptionsRelationManager::class, ['ownerRecord' => $phase, 'pageClass' => EditWorkOrderPhase::class])->callAction(TestAction::make('create')->table(), ['purchase_delivery_line_id' => $line->id, 'consumed_date' => '2026-11-04', 'quantity' => '2'])->assertHasNoFormErrors();
        $entry = MaterialConsumption::firstOrFail();
        $this->assertSame($phase->id, $entry->work_order_phase_id);
        $this->assertSame($phase->work_order_id, $entry->work_order_id);
        Livewire::test(EditMaterialConsumption::class, ['record' => $entry->id])->callAction('confirm')->assertHasNoActionErrors();
        $this->assertSame('posted', $entry->fresh()->status);
    }

    public function test_stock_product_creation_from_receipt_preserves_stock_context(): void
    {
        $this->actingAs(User::factory()->create());
        $note = PurchaseDeliveryNote::factory()->create();
        $unit = Unit::factory()->create();
        $page = Livewire::test(LinesRelationManager::class, ['ownerRecord' => $note, 'pageClass' => EditPurchaseDeliveryNote::class])->mountAction(TestAction::make('create')->table());
        $page->callAction(TestAction::make('createOption')->schemaComponent('product_id'), ['reference' => 'STOCK-NUEVO', 'name' => 'Material nuevo', 'base_unit_id' => $unit->id, 'type' => 'service', 'tracks_stock' => false])->assertHasNoFormErrors();
        $this->assertDatabaseHas('products', ['reference' => 'STOCK-NUEVO', 'type' => 'material', 'tracks_stock' => true]);
    }

    public function test_hourly_rate_and_work_entry_can_be_created_and_posted_from_filament(): void
    {
        $this->actingAs(User::factory()->create());
        $employee = Employee::factory()->create();
        $phase = WorkOrderPhase::factory()->create();
        WorkOrderEmployeeAssignment::factory()->create(['employee_id' => $employee->id, 'work_order_id' => $phase->work_order_id]);
        Livewire::test(CreateLaborCostRate::class)->fillForm(['employee_id' => $employee->id, 'hourly_cost' => '20', 'valid_from' => '2026-01-01'])->call('create')->assertHasNoFormErrors()->assertRedirect();
        $page = Livewire::test(CreateWorkTimeEntry::class)->fillForm(['work_order_id' => $phase->work_order_id, 'work_order_phase_id' => $phase->id, 'employee_id' => $employee->id, 'started_at' => '2026-11-04 09:00:00', 'ended_at' => '2026-11-04 11:00:00', 'break_minutes' => '0', 'description' => 'Instalación de material'])->call('create')->assertHasNoFormErrors()->assertRedirect();
        $entry = WorkTimeEntry::firstOrFail();
        Livewire::test(EditWorkTimeEntry::class, ['record' => $entry->id])->callAction('confirm')->assertHasNoActionErrors()->assertRedirect();
        $this->assertSame('2.000000', $entry->fresh()->hours);
        $this->assertSame('40.00', $entry->fresh()->total_cost);
    }

    public function test_phase_creation_respects_parent_update_authorization(): void
    {
        $this->actingAs(User::factory()->create());
        $phase = WorkOrderPhase::factory()->create();
        Gate::policy(WorkOrderPhase::class, DenyPhaseCostUpdates::class);
        Livewire::test(MaterialConsumptionsRelationManager::class, ['ownerRecord' => $phase, 'pageClass' => EditWorkOrderPhase::class])->assertActionHidden(TestAction::make('create')->table());
        $this->assertDatabaseCount('material_consumptions', 0);
    }

    public function test_global_consumption_creation_cannot_bypass_phase_update_permission(): void
    {
        $this->actingAs(User::factory()->create());
        $phase = WorkOrderPhase::factory()->create();
        $line = PurchaseDeliveryLine::factory()->create();
        app(ProjectCosting::class)->receive($line->deliveryNote);
        Gate::policy(WorkOrderPhase::class, DenyPhaseCostUpdates::class);
        Livewire::test(CreateMaterialConsumption::class)->fillForm(['work_order_id' => $phase->work_order_id, 'work_order_phase_id' => $phase->id, 'purchase_delivery_line_id' => $line->id, 'consumed_date' => '2026-11-04', 'quantity' => '1'])->call('create')->assertForbidden();
        $this->assertDatabaseCount('material_consumptions', 0);
    }
}

class DenyPhaseCostUpdates
{
    public function view(User $user, WorkOrderPhase $phase): bool
    {
        return true;
    }

    public function update(User $user, WorkOrderPhase $phase): bool
    {
        return false;
    }
}
