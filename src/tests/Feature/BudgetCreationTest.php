<?php

namespace Tests\Feature;

use App\Filament\Resources\Budgets\BudgetResource;
use App\Filament\Resources\Budgets\Pages\EditBudget;
use App\Filament\Resources\Budgets\Pages\ListBudgets;
use App\Filament\Resources\WorkOrders\Pages\ViewWorkOrderTree;
use App\Models\Budget;
use App\Models\Project;
use App\Models\ProjectChapter;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderLine;
use App\Support\Bc3BudgetImporter;
use App\Support\BudgetEditor;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class BudgetCreationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();
    }

    public function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    public function test_tree_displays_chapters_and_their_child_items(): void
    {
        $this->actingAs(User::factory()->create());
        [$order, $parent, $child, $one, $two] = $this->measurements();

        Livewire::test(ViewWorkOrderTree::class, ['record' => $order->id])
            ->assertSuccessful()->assertSee('Capítulo 01')->assertSee('Capítulo 01.01')
            ->assertSee('Demoliciones')->assertSee('Tabiques')->assertSee('Medición uno')->assertSee('Medición dos')
            ->assertSee('Crear presupuesto');
    }

    public function test_budget_copies_only_selected_items_and_changes_do_not_modify_measurements(): void
    {
        $this->actingAs(User::factory()->create());
        [$order, $parent, $child, $one, $two] = $this->measurements('measurement');

        Livewire::test(ViewWorkOrderTree::class, ['record' => $order->id])
            ->set('budgetName', 'Oferta parcial')
            ->set('selection.'.$one->id.'.quantity', '2.5')
            ->set('selection.'.$one->id.'.price', '12.34')
            ->set('selection.'.$one->id.'.unit', 'm²')
            ->set('selection.'.$two->id.'.included', false)
            ->callAction('createBudget')->assertNotified('Presupuesto creado')->assertHasNoActionErrors()
            ->assertDispatched('budget-created', fn (string $event, array $params): bool => $params['url'] === BudgetResource::getUrl('edit', ['record' => Budget::query()->sole()]));

        $budget = Budget::query()->sole();
        $this->assertSame('Oferta parcial', $budget->name);
        $this->assertCount(1, $budget->items);
        $this->assertSame($one->id, $budget->items[0]['source_line_id']);
        $this->assertSame('2.500000', $budget->items[0]['quantity']);
        $this->assertSame('12.340000', $budget->items[0]['price']);
        $this->assertSame('m²', $budget->items[0]['unit']);
        $this->assertSame('Ejemplo', $one->fresh()->unit_label);
        $this->assertSame('30.85', $budget->total());
        $this->assertSame('10.000000', $one->fresh()->planned_quantity);
        $this->assertSame('0.000000', $one->fresh()->estimated_unit_sale_price);
        $this->assertModelExists($two);
    }

    public function test_excluding_a_chapter_excludes_all_descendants(): void
    {
        $this->actingAs(User::factory()->create());
        [$order, $parent, $child, $one, $two] = $this->measurements();
        $other = WorkOrderLine::factory()->for($order)->create(['position' => 3, 'description' => 'Sin capítulo']);

        Livewire::test(ViewWorkOrderTree::class, ['record' => $order->id])
            ->call('toggleChapter', (string) $parent->id, false)
            ->assertSet('selection.'.$one->id.'.included', false)
            ->assertSet('selection.'.$two->id.'.included', false)
            ->callAction('createBudget')->assertNotified();

        $budget = Budget::query()->sole();
        $this->assertCount(1, $budget->items);
        $this->assertSame($other->id, $budget->items[0]['source_line_id']);
        $this->assertSame(3, $order->workOrderLines()->count());
    }

    public function test_budget_groups_original_resources_and_measurements_under_their_own_item_title(): void
    {
        $this->actingAs(User::factory()->create());
        [$order, $parent, $child, $one, $two] = $this->measurements();
        $one->update([
            'budget_breakdown' => [
                ['code' => 'AUX', 'name' => 'Mortero auxiliar', 'depth' => 1],
                ['code' => 'MAT', 'name' => 'Cemento original', 'parent_code' => 'AUX', 'depth' => 2, 'type' => '3', 'quantity' => 2, 'price' => 4],
            ],
            'measurement_details' => [['label' => 'Pared del salón', 'units' => 1, 'length' => 5, 'height' => 2, 'subtotal' => 10]],
        ]);
        $budget = app(BudgetEditor::class)->create($order, 'Presupuesto agrupado', [
            $one->id => ['included' => true, 'quantity' => '10', 'price' => '20'],
            $two->id => ['included' => true, 'quantity' => '1', 'price' => '5'],
        ]);
        $one->update(['budget_breakdown' => [['code' => 'NEW', 'name' => 'Recurso posterior', 'depth' => 1]]]);

        $page = Livewire::test(EditBudget::class, ['record' => $budget->id])
            ->assertSuccessful()->assertSee('205,00 EUR')->assertDontSee('Recurso posterior');

        $document = new \DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$page->html(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $firstItem = '//details[@data-budget-item="'.$one->id.'"]';
        $secondItem = '//details[@data-budget-item="'.$two->id.'"]';
        $this->assertStringContainsString('Medición uno', $xpath->query($firstItem.'/summary')->item(0)->textContent);
        $this->assertStringContainsString('Cemento original', $xpath->query($firstItem)->item(0)->textContent);
        $this->assertStringContainsString('Pared del salón', $xpath->query($firstItem)->item(0)->textContent);
        $this->assertSame(1, $xpath->query($firstItem.'//details[@data-resource-depth="1"]//details[@data-resource-depth="2"]')->length);
        $this->assertStringNotContainsString('Cemento original', $xpath->query($secondItem)->item(0)->textContent);
        $this->assertStringContainsString('Esta partida no tiene recursos descompuestos', $xpath->query($secondItem)->item(0)->textContent);
    }

    public function test_saved_budget_can_change_prices_and_remove_items_without_changing_source(): void
    {
        $this->actingAs(User::factory()->create());
        [$order, $parent, $child, $one, $two] = $this->measurements();
        Livewire::test(ViewWorkOrderTree::class, ['record' => $order->id])->callAction('createBudget')->assertNotified();
        $budget = Budget::query()->sole();

        Livewire::test(EditBudget::class, ['record' => $budget->id])
            ->set('selection.'.$one->id.'.price', '7.50')
            ->set('selection.'.$one->id.'.quantity', '3')
            ->set('selection.'.$two->id.'.included', false)
            ->callAction('saveBudget')->assertNotified('Presupuesto guardado');

        $budget->refresh();
        $this->assertCount(1, $budget->items);
        $this->assertSame('22.50', $budget->total());
        $this->assertSame(2, $order->workOrderLines()->count());
        $this->assertSame('10.000000', $one->fresh()->planned_quantity);
        Livewire::test(ListBudgets::class)->assertCanSeeTableRecords([$budget]);
    }

    public function test_price_and_quantity_changes_recalculate_the_preview_before_saving(): void
    {
        $this->actingAs(User::factory()->create());
        [$order, $parent, $child, $one] = $this->measurements();
        Livewire::test(ViewWorkOrderTree::class, ['record' => $order->id])->callAction('createBudget');
        $budget = Budget::query()->sole();

        $page = Livewire::test(EditBudget::class, ['record' => $budget->id])
            ->set('selection.'.$one->id.'.price', '12.34')
            ->assertSee('123,40 EUR')
            ->set('selection.'.$one->id.'.quantity', '2.5')
            ->assertSee('30,85 EUR');

        $document = new \DOMDocument;
        $document->loadHTML($page->html(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $this->assertStringContainsString('30,85 EUR', $xpath->query('//details[@data-chapter="'.$parent->id.'"]/summary')->item(0)->textContent);
        $this->assertSame('0.00', $budget->fresh()->total());

        $page->callAction('saveBudget')->assertNotified('Presupuesto guardado');

        $this->assertSame('30.85', $budget->fresh()->total());
    }

    public function test_budget_survives_deleting_the_source_draft(): void
    {
        $this->actingAs(User::factory()->create());
        [$order] = $this->measurements();
        Livewire::test(ViewWorkOrderTree::class, ['record' => $order->id])->callAction('createBudget')->assertNotified();
        $budget = Budget::query()->sole();

        $order->delete();

        $budget->refresh();
        $this->assertNull($budget->source_work_order_id);
        $this->assertCount(2, $budget->items);
        Livewire::test(EditBudget::class, ['record' => $budget->id])->assertSuccessful()->assertSee('Medición uno');
    }

    public function test_empty_or_invalid_budget_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        [$order, $parent, $child, $one] = $this->measurements();

        Livewire::test(ViewWorkOrderTree::class, ['record' => $order->id])
            ->set('selection.'.$one->id.'.price', '-1')
            ->callAction('createBudget')->assertHasErrors('selection.'.$one->id.'.price')
            ->assertNotDispatched('budget-created')->assertNoRedirect();
        Livewire::test(ViewWorkOrderTree::class, ['record' => $order->id])
            ->call('toggleChapter', (string) $parent->id, false)
            ->callAction('createBudget')->assertHasErrors('selection')
            ->assertNotDispatched('budget-created')->assertNoRedirect();

        $this->assertDatabaseCount('budgets', 0);
    }

    public function test_items_from_another_order_cannot_be_injected(): void
    {
        $this->actingAs(User::factory()->create());
        [$order] = $this->measurements();
        $foreign = WorkOrderLine::factory()->create();

        Livewire::test(ViewWorkOrderTree::class, ['record' => $order->id])
            ->set('selection.'.$foreign->id, ['included' => true, 'quantity' => '1', 'price' => '1'])
            ->callAction('createBudget')->assertHasErrors('selection');

        $this->assertDatabaseCount('budgets', 0);
    }

    public function test_amounts_round_each_item_exactly_before_adding_them(): void
    {
        $this->assertSame('0.02', BudgetEditor::total([
            ['quantity' => '1', 'price' => '0.005'],
            ['quantity' => '1', 'price' => '0.005'],
        ]));
        $this->assertSame('10.000.000.000.000.000.000.000,01', BudgetEditor::formatAmount('10000000000000000000000.01'));
    }

    public function test_creation_permission_is_required_and_source_tree_remains_readable(): void
    {
        $this->actingAs(User::factory()->create());
        [$order] = $this->measurements();
        Gate::policy(Budget::class, DenyBudgetCreation::class);

        Livewire::test(ViewWorkOrderTree::class, ['record' => $order->id])
            ->assertSuccessful()->assertActionHidden('createBudget');

        $this->assertDatabaseCount('budgets', 0);
    }

    public function test_a_budget_can_be_prepared_from_the_original_bc3_tree(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();
        $order = app(Bc3BudgetImporter::class)->import($project, base_path('tests/Fixtures/architect-budget.bc3'), 'Mediciones BC3');
        $chapter = $project->projectChapters()->where('code', 'D01')->sole();
        $first = $order->workOrderLines()->where('source_code', 'D0001.0010')->sole();
        $excludedCount = $order->workOrderLines()->where('project_chapter_id', $chapter->id)->count();

        Livewire::test(ViewWorkOrderTree::class, ['record' => $order->id])
            ->assertSuccessful()->assertSee('ACTUACIONES PREVIAS')->assertSee('DEMOLICIONES')
            ->call('toggleChapter', (string) $chapter->id, false)
            ->set('selection.'.$first->id.'.quantity', '2')
            ->set('selection.'.$first->id.'.price', '10')
            ->callAction('createBudget')->assertNotified('Presupuesto creado');

        $budget = Budget::query()->sole();
        $this->assertCount(149 - $excludedCount, $budget->items);
        $this->assertSame('20.00', $budget->total());
        $this->assertSame(149, $order->workOrderLines()->count());
    }

    public function test_removing_a_source_item_while_the_editor_is_open_cannot_shift_the_selection_to_another_item(): void
    {
        $this->actingAs(User::factory()->create());
        [$order, $parent, $child, $one] = $this->measurements();
        $page = Livewire::test(ViewWorkOrderTree::class, ['record' => $order->id]);
        $one->delete();

        $page->callAction('createBudget')->assertHasErrors('selection');

        $this->assertDatabaseCount('budgets', 0);
    }

    public function test_budget_edit_permission_is_checked_on_direct_urls(): void
    {
        $this->actingAs(User::factory()->create());
        $budget = Budget::factory()->create();
        Gate::policy(Budget::class, DenyBudgetEditing::class);

        $this->get(BudgetResource::getUrl('edit', ['record' => $budget]))->assertForbidden();
    }

    /** @return array{WorkOrder, ProjectChapter, ProjectChapter, WorkOrderLine, WorkOrderLine} */
    private function measurements(string $purpose = 'intervention'): array
    {
        $order = WorkOrder::factory()->create(['purpose' => $purpose]);
        $parent = ProjectChapter::factory()->for($order->project)->create(['code' => '01', 'name' => 'Demoliciones']);
        $child = ProjectChapter::factory()->for($order->project)->create(['code' => '01.01', 'name' => 'Tabiques', 'parent_id' => $parent->id]);
        $one = WorkOrderLine::factory()->for($order)->for($parent, 'projectChapter')->create(['position' => 1, 'description' => 'Medición uno', 'planned_quantity' => '10']);
        $two = WorkOrderLine::factory()->for($order)->for($child, 'projectChapter')->create(['position' => 2, 'description' => 'Medición dos']);

        return [$order, $parent, $child, $one, $two];
    }
}

class DenyBudgetCreation
{
    public function create(User $user): bool
    {
        return false;
    }
}

class DenyBudgetEditing
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function update(User $user, Budget $budget): bool
    {
        return false;
    }
}
