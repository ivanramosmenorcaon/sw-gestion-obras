<?php

namespace Tests\Feature;

use App\Filament\Resources\WorkOrderLines\Pages\ListWorkOrderLines;
use App\Filament\Resources\WorkOrderLines\Pages\ViewWorkOrderLine;
use App\Filament\Resources\WorkOrderLines\WorkOrderLineResource;
use App\Filament\Resources\WorkOrders\Pages\EditWorkOrder;
use App\Filament\Resources\WorkOrders\RelationManagers\WorkOrderLinesRelationManager;
use App\Models\Project;
use App\Models\ProjectChapter;
use App\Models\User;
use App\Models\WorkOrderLine;
use App\Support\Bc3BudgetImporter;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class WorkOrderLineBreakdownPageTest extends TestCase
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

    public function test_a_partida_displays_its_chapter_path_and_nested_resources_without_losing_repeated_codes(): void
    {
        $this->actingAs(User::factory()->create());
        $line = $this->lineWithBreakdown();

        $page = Livewire::test(ViewWorkOrderLine::class, ['record' => $line->id])
            ->assertSuccessful()
            ->assertSee('Árbol de descomposición')
            ->assertSee('Capítulo 01 · Albañilería')
            ->assertSee('Capítulo 01.01 · Tabiques')
            ->assertSee('Auxiliar compuesto')->assertSee('Mano de obra')->assertSee('Material')->assertSee('Maquinaria')
            ->assertSee('Mezcla de mortero')->assertSee('Oficial')->assertSee('Arena')->assertSee('Hormigonera')
            ->assertSee('data-resource-depth="2"', false)
            ->assertSee('data-resource-key="1"', false)->assertSee('data-resource-key="3"', false)
            ->assertSee('12,500000')->assertSee('Zona de descuento')->assertSee('-1,000000');

        $document = new \DOMDocument;
        $document->loadHTML($page->html(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//*[@data-resource-key="1"]/ancestor::*[@data-resource-key="0"]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-resource-key="3"]/ancestor::*[@data-resource-key="0"]')->length);
    }

    public function test_original_bc3_import_has_a_working_graphical_page(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();
        $order = app(Bc3BudgetImporter::class)->import($project, base_path('tests/Fixtures/architect-budget.bc3'), 'Presupuesto');
        $line = $order->workOrderLines()->where('source_code', 'D0104.0010')->sole();

        $this->get(WorkOrderLineResource::getUrl('view', ['record' => $line]))
            ->assertOk()->assertSee('D0104.0010')->assertSee('Mano de obra')->assertSee('16,244000');
    }

    public function test_line_without_bc3_breakdown_has_an_informative_empty_state(): void
    {
        $this->actingAs(User::factory()->create());
        $line = WorkOrderLine::factory()->create();

        Livewire::test(ViewWorkOrderLine::class, ['record' => $line->id])
            ->assertSuccessful()->assertSee('Esta partida no tiene recursos descompuestos.')
            ->assertSee('Sin líneas de medición desglosadas.');
    }

    public function test_partida_lists_and_relation_managers_link_to_the_graphical_view(): void
    {
        $this->actingAs(User::factory()->create());
        $line = $this->lineWithBreakdown();
        $url = WorkOrderLineResource::getUrl('view', ['record' => $line]);

        Livewire::test(ListWorkOrderLines::class)
            ->assertActionHasUrl(TestAction::make('viewBreakdown')->table($line), $url);
        $manager = Livewire::test(WorkOrderLinesRelationManager::class, ['ownerRecord' => $line->workOrder, 'pageClass' => EditWorkOrder::class])
            ->assertActionHasUrl(TestAction::make('viewBreakdown')->table($line), $url);

        $document = new \DOMDocument;
        $document->loadHTML($manager->html(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//tbody/tr/td[1]//a[@href="'.$url.'"]')->length);
    }

    public function test_view_access_is_denied_even_when_the_url_is_known(): void
    {
        $this->actingAs(User::factory()->create());
        $line = $this->lineWithBreakdown();
        Gate::policy(WorkOrderLine::class, DenyBudgetBreakdownView::class);

        $this->get(WorkOrderLineResource::getUrl('view', ['record' => $line]))->assertForbidden();
        Livewire::test(ListWorkOrderLines::class)->assertActionHidden(TestAction::make('viewBreakdown')->table($line));
    }

    public function test_imported_descriptions_are_escaped_in_the_tree_and_measurements(): void
    {
        $this->actingAs(User::factory()->create());
        $line = $this->lineWithBreakdown();
        $payload = '<script>alert("xss")</script>';
        $breakdown = $line->budget_breakdown;
        $breakdown[0]['name'] = $payload;
        $breakdown[0]['description'] = $payload;
        $line->update(['description' => $payload, 'budget_breakdown' => $breakdown, 'measurement_details' => [['label' => $payload, 'subtotal' => 1]]]);

        $this->get(WorkOrderLineResource::getUrl('view', ['record' => $line]))
            ->assertOk()->assertSee($payload)->assertDontSee($payload, false);
    }

    public function test_guests_cannot_access_the_graphical_page(): void
    {
        $line = WorkOrderLine::factory()->create();

        $this->get(WorkOrderLineResource::getUrl('view', ['record' => $line]))->assertRedirect(Filament::getLoginUrl());
    }

    private function lineWithBreakdown(): WorkOrderLine
    {
        $line = WorkOrderLine::factory()->create(['source_code' => 'P01', 'planned_quantity' => 3, 'estimated_unit_sale_price' => 12.5]);
        $chapter = ProjectChapter::factory()->for($line->project)->create(['code' => '01', 'name' => 'Albañilería']);
        $subchapter = ProjectChapter::factory()->for($line->project)->create(['code' => '01.01', 'name' => 'Tabiques', 'parent_id' => $chapter->id]);
        $line->update([
            'project_chapter_id' => $subchapter->id,
            'budget_breakdown' => [
                ['code' => 'A1', 'name' => 'Mezcla de mortero', 'type' => '0', 'depth' => 1, 'parent_code' => 'P01', 'unit' => 'm3', 'factor' => 1, 'quantity' => 0.5, 'price' => 5, 'description' => 'Preparación de mezcla'],
                ['code' => 'L1', 'name' => 'Oficial', 'type' => '1', 'depth' => 2, 'parent_code' => 'A1', 'unit' => 'h', 'price' => 20],
                ['code' => 'M1', 'name' => 'Arena', 'type' => '3', 'depth' => 2, 'parent_code' => 'A1', 'unit' => 'kg', 'price' => 2],
                ['code' => 'L1', 'name' => 'Oficial', 'type' => '1', 'depth' => 1, 'parent_code' => 'P01', 'unit' => 'h', 'price' => 20],
                ['code' => 'Q1', 'name' => 'Hormigonera', 'type' => '2', 'depth' => 1, 'parent_code' => 'P01', 'unit' => 'h', 'price' => 15],
            ],
            'measurement_details' => [
                ['label' => 'Superficie', 'units' => 1, 'length' => 4, 'subtotal' => 4],
                ['label' => 'Zona de descuento', 'units' => -1, 'length' => 1, 'subtotal' => -1],
            ],
        ]);

        return $line;
    }
}

class DenyBudgetBreakdownView
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WorkOrderLine $line): bool
    {
        return false;
    }
}
