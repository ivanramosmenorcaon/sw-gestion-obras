<?php

namespace Tests\Feature;

use App\Filament\Resources\ProjectItems\Pages\ManageProjectItems;
use App\Filament\Resources\ProjectItems\ProjectItemResource;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\RelationManagers\ProjectItemsRelationManager;
use App\Filament\Resources\WorkOrders\Pages\EditWorkOrder;
use App\Filament\Resources\WorkOrders\RelationManagers\WorkOrderLinesRelationManager;
use App\Filament\Resources\WorkTimeEntries\Pages\CreateWorkTimeEntry;
use App\Models\Project;
use App\Models\ProjectChapter;
use App\Models\ProjectItem;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderLine;
use App\Models\WorkProgress;
use App\Support\BudgetEditor;
use App\Support\BudgetWorkflow;
use App\Support\OperationsManager;
use App\Support\ProjectScope;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ProjectInterventionsTest extends TestCase
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

    public function test_one_project_item_can_be_split_across_interventions_without_copying_the_item(): void
    {
        $this->actingAs(User::factory()->create());
        $item = ProjectItem::factory()->create();
        $first = WorkOrder::factory()->create(['project_id' => $item->project_id]);
        $second = WorkOrder::factory()->create(['project_id' => $item->project_id]);

        $one = app(ProjectScope::class)->assign($first, $item, '20');
        $two = app(ProjectScope::class)->assign($second, $item, '60');

        $this->assertDatabaseCount('project_items', 1);
        $this->assertSame($item->id, $one->project_item_id);
        $this->assertSame($item->id, $two->project_item_id);
        $this->assertSame('0.000000', $item->remainingQuantity());
        $this->assertSame('40.000000', $one->estimated_unit_sale_price);
        $this->assertSame(1, $first->workOrderPhases()->count());
    }

    public function test_one_intervention_can_include_several_project_items_and_reuses_its_general_phase(): void
    {
        $this->actingAs(User::factory()->create());
        $one = ProjectItem::factory()->create();
        $two = ProjectItem::factory()->create(['project_id' => $one->project_id]);
        $order = WorkOrder::factory()->create(['project_id' => $one->project_id]);

        app(ProjectScope::class)->assign($order, $one, '20');
        app(ProjectScope::class)->assign($order, $two, '10');

        $this->assertSame([1, 2], $order->workOrderLines()->orderBy('position')->pluck('position')->all());
        $this->assertSame(1, $order->workOrderPhases()->count());
        $this->assertSame('60.000000', $one->remainingQuantity());
        $this->assertSame('70.000000', $two->remainingQuantity());
    }

    public function test_over_allocation_is_rejected_at_the_last_decimal_and_keeps_the_existing_scope(): void
    {
        $this->actingAs(User::factory()->create());
        $item = ProjectItem::factory()->create();
        $order = WorkOrder::factory()->create(['project_id' => $item->project_id]);
        app(ProjectScope::class)->assign($order, $item, '60.000001');

        try {
            app(ProjectScope::class)->assign($order, $item, '20');
            $this->fail('Se permitió encargar más cantidad de la prevista.');
        } catch (ValidationException $exception) {
            $this->assertSame('Supera la cantidad pendiente de encargar de esta partida. Reduzca otra orden o apruebe una ampliación.', $exception->errors()['planned_quantity'][0]);
        }

        $this->assertSame('19.999999', $item->remainingQuantity());
        $this->assertSame(1, $order->workOrderLines()->count());
    }

    public function test_project_mismatch_rolls_back_the_line_and_automatic_phase(): void
    {
        $this->actingAs(User::factory()->create());
        $item = ProjectItem::factory()->create();
        $order = WorkOrder::factory()->create();

        try {
            app(ProjectScope::class)->assign($order, $item, '20');
            $this->fail('Se permitió encargar una partida de otra obra.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('project_item_id', $exception->errors());
        }

        $this->assertSame(0, $order->workOrderLines()->count());
        $this->assertSame(0, $order->workOrderPhases()->count());
    }

    public function test_cancellation_releases_quantity_but_reopening_cannot_overbook_it(): void
    {
        $this->actingAs(User::factory()->create());
        $item = ProjectItem::factory()->create();
        $first = WorkOrder::factory()->create(['project_id' => $item->project_id]);
        app(ProjectScope::class)->assign($first, $item, '80');
        $first->update(['status' => 'cancelled']);
        $this->assertSame('80.000000', $item->remainingQuantity());
        $second = WorkOrder::factory()->create(['project_id' => $item->project_id]);
        app(ProjectScope::class)->assign($second, $item, '80');

        try {
            $first->update(['status' => 'draft']);
            $this->fail('Se permitió reabrir una orden con cantidad ya encargada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame('cancelled', $first->fresh()->status);
        $this->assertSame('0.000000', $item->remainingQuantity());
    }

    #[TestWith(['project_item_id', null])]
    #[TestWith(['estimated_unit_sale_price', '1'])]
    #[TestWith(['description', 'Otro alcance'])]
    public function test_direct_line_updates_cannot_detach_or_rewrite_the_project_item(string $field, mixed $value): void
    {
        $this->actingAs(User::factory()->create());
        $item = ProjectItem::factory()->create();
        $order = WorkOrder::factory()->create(['project_id' => $item->project_id]);
        $line = app(ProjectScope::class)->assign($order, $item, '20');

        $this->expectException(ValidationException::class);
        $line->update([$field => $value]);
    }

    public function test_quantity_cannot_be_reduced_below_confirmed_progress(): void
    {
        $this->actingAs(User::factory()->create());
        $item = ProjectItem::factory()->create();
        $order = WorkOrder::factory()->create(['project_id' => $item->project_id]);
        $line = app(ProjectScope::class)->assign($order, $item, '20');
        $progress = WorkProgress::factory()->create(['project_id' => $item->project_id, 'work_order_line_id' => $line->id, 'quantity' => '15']);
        app(OperationsManager::class)->post($progress);

        $this->expectException(ValidationException::class);
        $line->update(['planned_quantity' => '10']);
    }

    public function test_accepted_budget_publishes_the_existing_item_once_with_chapter_and_without_creating_orders(): void
    {
        $this->actingAs(User::factory()->create());
        $unit = Unit::factory()->create(['symbol' => 'm²', 'magnitude' => 'area']);
        $chapter = ProjectChapter::factory()->create(['code' => 'PAV']);
        $item = ProjectItem::factory()->create(['project_id' => $chapter->project_id, 'project_chapter_id' => $chapter->id, 'unit_id' => $unit->id, 'base_unit_id' => $unit->id, 'discount_percentage' => '10']);
        $budget = app(BudgetEditor::class)->fromProjectItems(new Collection([$item]), 'Oferta de pavimento');
        $workflow = app(BudgetWorkflow::class);
        $workflow->send($budget);
        $workflow->accept($budget, 'Correo de aceptación del cliente');

        $first = $workflow->publish($budget);
        $again = $workflow->publish($budget);

        $this->assertSame($item->id, $first->sole()->id);
        $this->assertSame($item->id, $again->sole()->id);
        $this->assertDatabaseCount('project_items', 1);
        $this->assertDatabaseCount('work_orders', 0);
        $this->assertSame($chapter->id, $item->fresh()->project_chapter_id);
        $this->assertSame($budget->id, $item->fresh()->source_budget_id);
        $this->assertSame('10.0000', $item->fresh()->discount_percentage);
        $this->assertSame('80.000000', $item->remainingQuantity());
    }

    public function test_measurement_source_does_not_reserve_execution_quantity(): void
    {
        $this->actingAs(User::factory()->create());
        $order = WorkOrder::factory()->create(['purpose' => 'measurement']);
        $source = WorkOrderLine::factory()->create(['project_id' => $order->project_id, 'work_order_id' => $order->id, 'planned_quantity' => '80']);

        $item = app(ProjectScope::class)->captureLine($source);

        $this->assertSame('80.000000', $item->remainingQuantity());
        $this->assertSame(0, $order->workOrderPhases()->count());
        $this->assertSame($source->id, $item->workOrderLines()->sole()->id);
    }

    public function test_repeated_capture_keeps_a_single_item_and_a_single_general_phase(): void
    {
        $line = WorkOrderLine::factory()->create(['planned_quantity' => '12']);
        $scope = app(ProjectScope::class);

        $one = $scope->captureLine($line);
        $two = $scope->captureLine($line->fresh());

        $this->assertSame($one->id, $two->id);
        $this->assertDatabaseCount('project_items', 1);
        $this->assertSame(1, $line->workOrder->workOrderPhases()->count());
        $this->assertNotNull($line->fresh()->work_order_phase_id);
    }

    public function test_time_form_selects_the_only_phase_of_a_simple_intervention(): void
    {
        $this->actingAs(User::factory()->create());
        $item = ProjectItem::factory()->create();
        $order = WorkOrder::factory()->create(['project_id' => $item->project_id]);
        $line = app(ProjectScope::class)->assign($order, $item, '20');

        Livewire::test(CreateWorkTimeEntry::class)
            ->set('data.work_order_id', $order->id)
            ->assertFormSet(['work_order_phase_id' => $line->work_order_phase_id]);
    }

    public function test_cancelled_order_cannot_receive_production_after_its_quantity_is_released(): void
    {
        $this->actingAs(User::factory()->create());
        $item = ProjectItem::factory()->create();
        $order = WorkOrder::factory()->create(['project_id' => $item->project_id]);
        $line = app(ProjectScope::class)->assign($order, $item, '20');
        $order->update(['status' => 'cancelled']);

        $this->expectException(ValidationException::class);
        WorkProgress::factory()->create(['project_id' => $item->project_id, 'work_order_line_id' => $line->id, 'quantity' => '10']);
    }

    public function test_project_and_order_screens_expose_items_and_quantity_assignment(): void
    {
        $this->actingAs(User::factory()->create());
        $item = ProjectItem::factory()->create();
        $order = WorkOrder::factory()->create(['project_id' => $item->project_id]);

        Livewire::test(ManageProjectItems::class)->assertSuccessful()->assertCanSeeTableRecords([$item]);
        Livewire::test(ProjectItemsRelationManager::class, ['ownerRecord' => $item->project, 'pageClass' => EditProject::class])->assertSuccessful()->assertCanSeeTableRecords([$item]);
        Livewire::test(WorkOrderLinesRelationManager::class, ['ownerRecord' => $order, 'pageClass' => EditWorkOrder::class])
            ->callTableAction('assignProjectItem', data: ['project_item_id' => $item->id, 'project_id' => $item->project_id, 'planned_quantity' => '20'])
            ->assertHasNoTableActionErrors();

        $this->assertSame($item->id, $order->workOrderLines()->sole()->project_item_id);
        $this->assertSame('60.000000', $item->remainingQuantity());
    }

    public function test_project_items_are_grouped_by_chapter_with_a_group_for_unassigned_items(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();
        $firstChapter = ProjectChapter::factory()->for($project)->create(['code' => '01', 'name' => 'Acabados']);
        $secondChapter = ProjectChapter::factory()->for($project)->create(['code' => '02', 'name' => 'Acabados']);
        $items = ProjectItem::factory()->for($project)->count(2)->create(['project_chapter_id' => $firstChapter->id]);
        $items->push(ProjectItem::factory()->for($project)->create(['project_chapter_id' => $secondChapter->id]));
        $items->push(ProjectItem::factory()->for($project)->create(['project_chapter_id' => null]));
        $foreignItem = ProjectItem::factory()->create();

        $page = Livewire::test(ProjectItemsRelationManager::class, ['ownerRecord' => $project, 'pageClass' => EditProject::class])
            ->assertSuccessful()->assertCanSeeTableRecords($items)->assertCanNotSeeTableRecords([$foreignItem])
            ->assertSee('01 · Acabados')->assertSee('02 · Acabados')->assertSee('Sin capítulo asignado');

        $document = new \DOMDocument;
        $document->loadHTML($page->html(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $this->assertSame(3, $xpath->query('//tr[contains(concat(" ", normalize-space(@class), " "), " fi-ta-group-header-row ")]')->length);

        Livewire::test(ManageProjectItems::class)->assertSuccessful()
            ->assertCanSeeTableRecords($items)->assertSee('01 · Acabados')->assertSee('02 · Acabados')->assertSee('Sin capítulo asignado');
    }

    public function test_manager_cannot_view_items_of_another_project(): void
    {
        $item = ProjectItem::factory()->create();
        $manager = User::factory()->create(['role' => 'manager']);
        $this->actingAs($manager);

        Livewire::test(ManageProjectItems::class)->assertSuccessful()->assertCanNotSeeTableRecords([$item]);
        $this->assertFalse(ProjectItemResource::canView($item));
    }

    public function test_nested_item_creation_fixes_the_project_and_selects_the_created_item(): void
    {
        $this->actingAs(User::factory()->create());
        $order = WorkOrder::factory()->create();
        $other = Project::factory()->create();
        $unit = Unit::factory()->create();
        $page = Livewire::test(WorkOrderLinesRelationManager::class, ['ownerRecord' => $order, 'pageClass' => EditWorkOrder::class])
            ->mountAction(TestAction::make('assignProjectItem')->table());

        $page->callAction(TestAction::make('createOption')->schemaComponent('project_item_id'), [
            'project_id' => $other->id, 'description' => 'Partida creada desde la intervención',
            'unit_id' => $unit->id, 'base_unit_id' => $unit->id, 'unit_label' => $unit->symbol,
            'type' => 'service', 'position' => 1, 'planned_quantity' => '12',
        ])->assertHasNoFormErrors();

        $item = ProjectItem::where('description', 'Partida creada desde la intervención')->sole();
        $this->assertSame($order->project_id, $item->project_id);
        $page->set('mountedActions.0.data.planned_quantity', '6')->callMountedAction()->assertHasNoActionErrors();
        $this->assertSame($item->id, $order->workOrderLines()->sole()->project_item_id);
    }

    public function test_nested_item_creation_requires_the_related_resource_permission(): void
    {
        $this->actingAs(User::factory()->create());
        $order = WorkOrder::factory()->create();
        Gate::policy(ProjectItem::class, DenyInlineProjectItemCreation::class);

        Livewire::test(WorkOrderLinesRelationManager::class, ['ownerRecord' => $order, 'pageClass' => EditWorkOrder::class])
            ->mountAction(TestAction::make('assignProjectItem')->table())
            ->assertActionHidden(TestAction::make('createOption')->schemaComponent('project_item_id'));

        $this->assertDatabaseCount('project_items', 0);
    }

    public function test_upgrade_preserves_existing_line_identity_and_creates_its_project_item(): void
    {
        $line = WorkOrderLine::factory()->create(['planned_quantity' => '12.345678', 'estimated_unit_sale_price' => '45.123456']);
        $migration = require database_path('migrations/2026_11_07_100826_create_project_items_table.php');
        $migration->down();

        $migration->up();
        $migration->up();

        $stored = $line->fresh();
        $this->assertSame($line->id, $stored->id);
        $this->assertSame($line->work_order_id, $stored->work_order_id);
        $this->assertSame('12.345678', $stored->planned_quantity);
        $this->assertSame('45.123456', $stored->estimated_unit_sale_price);
        $this->assertSame('12.345678', $stored->projectItem->planned_quantity);
        $this->assertSame('0.000000', $stored->projectItem->remainingQuantity());
        $this->assertDatabaseCount('project_items', 1);
    }
}

class DenyInlineProjectItemCreation
{
    public function create(User $user): bool
    {
        return false;
    }
}
