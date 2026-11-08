<?php

namespace Tests\Feature;

use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\RelationManagers\MeasurementsRelationManager;
use App\Filament\Resources\Projects\RelationManagers\WorkOrdersRelationManager;
use App\Filament\Resources\WorkOrders\Pages\ViewWorkOrderTree;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\ArchitectMeasurementSpreadsheet;
use App\Support\BudgetEditor;
use App\Support\BudgetWorkflow;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectMeasurementsTest extends TestCase
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

    public function test_measurements_tab_imports_and_archives_original_without_creating_contracted_items(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();
        $content = file_get_contents(base_path('tests/Fixtures/architect-measurements.xlsx'));
        $upload = UploadedFile::fake()->createWithContent('Mediciones originales.xlsx', $content);

        Livewire::test(MeasurementsRelationManager::class, ['ownerRecord' => $project, 'pageClass' => EditProject::class])
            ->callAction(TestAction::make('importArchitectMeasurements')->table(), ['file' => $upload, 'name' => 'Proyecto original'])
            ->assertHasNoActionErrors()->assertNotified('Mediciones importadas');

        $order = $project->measurements()->sole();
        $this->assertSame('Mediciones originales.xlsx', $order->measurement_source_name);
        $this->assertSame($content, Storage::disk('local')->get($order->measurement_source_path));
        $this->assertSame(0, $project->projectItems()->count());
        $this->assertSame(149, $order->workOrderLines()->whereNull('project_item_id')->count());

        Livewire::test(ViewWorkOrderTree::class, ['record' => $order->id])
            ->assertSee('Capítulos y partidas')->assertSee('Medición original:')
            ->callAction('downloadSource')->assertFileDownloaded('Mediciones originales.xlsx');

        Livewire::test(EditProject::class, ['record' => $project->id])
            ->callAction('importArchitectMeasurements', ['file' => $upload, 'name' => 'Duplicado'])
            ->assertHasActionErrors(['file']);
        $this->assertCount(1, Storage::disk('local')->allFiles('measurements'));
        $this->assertSame(1, $project->measurements()->count());
    }

    public function test_tabs_separate_measurements_from_interventions_and_other_projects(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();
        $measurement = WorkOrder::factory()->create(['project_id' => $project->id, 'purpose' => 'measurement']);
        $intervention = WorkOrder::factory()->create(['project_id' => $project->id]);
        $other = WorkOrder::factory()->create(['purpose' => 'measurement']);

        Livewire::test(MeasurementsRelationManager::class, ['ownerRecord' => $project, 'pageClass' => EditProject::class])
            ->assertCanSeeTableRecords([$measurement])->assertCanNotSeeTableRecords([$intervention, $other]);
        Livewire::test(WorkOrdersRelationManager::class, ['ownerRecord' => $project, 'pageClass' => EditProject::class])
            ->assertCanSeeTableRecords([$intervention])->assertCanNotSeeTableRecords([$measurement, $other]);
        Livewire::test(ViewWorkOrderTree::class, ['record' => $measurement->id])
            ->assertActionHidden('downloadSource')->assertSee('sin archivo original archivado');
    }

    public function test_selected_imported_items_only_become_project_scope_after_acceptance_and_keep_original_measurements(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();
        $order = app(ArchitectMeasurementSpreadsheet::class)->import($project, base_path('tests/Fixtures/architect-measurements.xlsx'), 'Original');
        $line = $order->workOrderLines()->first();
        $originalQuantity = $line->planned_quantity;
        $details = $line->measurement_details;
        $selection = [$line->id => ['included' => true, 'quantity' => '2', 'price' => '10', 'unit' => $line->unit_label]];
        $budget = app(BudgetEditor::class)->create($order, 'Oferta seleccionada', $selection);
        $this->assertSame(0, $project->projectItems()->count());
        $workflow = app(BudgetWorkflow::class);
        $workflow->send($budget);
        $workflow->accept($budget, 'Aceptación escrita del cliente');
        $items = $workflow->publish($budget);
        $this->assertCount(1, $items);
        $this->assertSame('2.000000', $items->first()->planned_quantity);
        $this->assertSame($details, $items->first()->measurement_details);
        $this->assertSame($originalQuantity, $line->fresh()->planned_quantity);
        $this->assertSame($items->first()->id, $line->fresh()->project_item_id);
        $this->assertSame(1, $project->projectItems()->count());
        $this->assertSame($items->modelKeys(), $workflow->publish($budget)->modelKeys());
        $this->assertSame(0, $project->workOrders()->where('purpose', 'intervention')->count());

        $another = app(BudgetEditor::class)->create($order, 'Otra oferta', $selection);
        $workflow->send($another);
        $workflow->accept($another, 'Otra aceptación del cliente');
        $this->expectException(ValidationException::class);
        $workflow->publish($another);
    }

    public function test_detail_selection_rejects_lines_from_another_import(): void
    {
        $this->actingAs(User::factory()->create());
        $importer = app(ArchitectMeasurementSpreadsheet::class);
        $one = $importer->import(Project::factory()->create(), base_path('tests/Fixtures/architect-measurements.xlsx'), 'Una');
        $other = $importer->import(Project::factory()->create(), base_path('tests/Fixtures/architect-measurements.xlsx'), 'Otra');
        Livewire::test(ViewWorkOrderTree::class, ['record' => $one->id])
            ->call('selectLine', $one->workOrderLines()->orderBy('position')->skip(1)->first()->id)->assertSuccessful()
            ->call('selectLine', $other->workOrderLines()->first()->id)->assertNotFound();
    }

    public function test_imported_measurement_content_cannot_be_overwritten(): void
    {
        $this->actingAs(User::factory()->create());
        $order = app(ArchitectMeasurementSpreadsheet::class)->import(Project::factory()->create(), base_path('tests/Fixtures/architect-measurements.xlsx'), 'Original');
        $line = $order->workOrderLines()->first();
        $quantity = $line->planned_quantity;

        try {
            $line->update(['planned_quantity' => '999']);
            $this->fail('Se modificó la medición original.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('se conservan como origen', $exception->errors()['description'][0]);
        }
        $this->assertSame($quantity, $line->fresh()->planned_quantity);
    }

    public function test_archived_file_that_is_no_longer_available_returns_not_found(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create());
        $order = WorkOrder::factory()->create(['purpose' => 'measurement']);
        $order->forceFill(['measurement_source_path' => 'measurements/missing.xlsx', 'measurement_source_name' => 'Original.xlsx'])->save();

        Livewire::test(ViewWorkOrderTree::class, ['record' => $order->id])
            ->call('mountAction', 'downloadSource')->assertNotFound();
    }

    public function test_manager_can_read_measurements_of_their_own_project(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $project = Project::factory()->create(['manager_id' => $manager->id]);
        $order = WorkOrder::factory()->create(['project_id' => $project->id, 'purpose' => 'measurement']);
        $this->actingAs($manager);

        Livewire::test(MeasurementsRelationManager::class, ['ownerRecord' => $project, 'pageClass' => EditProject::class])
            ->assertCanSeeTableRecords([$order]);
        Livewire::test(ViewWorkOrderTree::class, ['record' => $order->id])->assertSuccessful();
    }

    public function test_manager_cannot_open_or_download_measurements_from_another_project(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $order = WorkOrder::factory()->create(['purpose' => 'measurement']);
        $this->actingAs($manager);
        Livewire::test(ViewWorkOrderTree::class, ['record' => $order->id])->assertNotFound();
    }
}
