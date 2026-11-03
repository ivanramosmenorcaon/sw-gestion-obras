<?php

namespace Tests\Feature;

use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\WorkOrderLines\Pages\EditWorkOrderLine;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\Project;
use App\Models\ProjectChapter;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderLine;
use App\Support\ArchitectMeasurementSpreadsheet;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ArchitectMeasurementSpreadsheetTest extends TestCase
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

    #[DataProvider('formats')]
    public function test_architect_example_is_imported_with_all_chapters_items_and_measurements(string $extension): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();
        $other = Project::factory()->create();
        $existing = ProjectChapter::factory()->for($project)->create(['code' => '01', 'name' => 'Conservar capítulo']);
        $path = base_path('tests/Fixtures/architect-measurements.'.$extension);
        $upload = UploadedFile::fake()->createWithContent('mediciones.'.$extension, file_get_contents($path));

        $page = Livewire::test(EditProject::class, ['record' => $project->id])
            ->callAction('importArchitectMeasurements', ['file' => $upload, 'name' => 'Mediciones Rovellada'])
            ->assertHasNoActionErrors()->assertNotified('Mediciones importadas');

        $order = $project->workOrders()->sole();
        $page->assertRedirect(WorkOrderResource::getUrl('edit', ['record' => $order]));
        $this->assertSame('draft', $order->status);
        $this->assertSame(23, $project->projectChapters()->count());
        $this->assertSame('Conservar capítulo', $existing->fresh()->name);
        $this->assertSame(149, $order->workOrderLines()->count());
        $this->assertSame(347, $order->workOrderLines()->get()->sum(fn (WorkOrderLine $line): int => count($line->measurement_details)));
        $this->assertSame(0, $other->workOrders()->count());
        $this->assertSame(0, $other->projectChapters()->count());
        $this->assertSame(0, $other->workOrderLines()->count());
        $this->assertSame(5, Unit::query()->count());

        $line = $order->workOrderLines()->where('source_code', '0207')->sole();
        $this->assertSame('16.244000', $line->planned_quantity);
        $this->assertSame('0.000000', $line->estimated_unit_sale_price);
        $this->assertSame('0.000000', $line->estimated_unit_cost);
        $this->assertSame('02', $line->projectChapter->code);
        $this->assertSame('square_meter', $line->unit->code);
        $this->assertSame(-1.68, $line->measurement_details[1]['subtotal']);
        $this->assertStringContainsString('Demolición manual de tabique de marés', $line->description);
        $this->assertStringContainsString('Sin precio unitario', $line->notes);
        Livewire::test(EditWorkOrderLine::class, ['record' => $line->id])
            ->assertSuccessful()->assertSee('Desglose de medición del arquitecto');
    }

    /** @return array<string, array{string}> */
    public static function formats(): array
    {
        return ['Excel antiguo' => ['xls'], 'Excel moderno' => ['xlsx']];
    }

    public function test_both_formats_preserve_identical_values_and_original_row_numbers(): void
    {
        $service = app(ArchitectMeasurementSpreadsheet::class);

        $original = $service->inspect(base_path('tests/Fixtures/architect-measurements.xls'));
        $converted = $service->inspect(base_path('tests/Fixtures/architect-measurements.xlsx'));

        $this->assertSame($original, $converted);
        $this->assertSame(6, $original['items'][0]['source_row']);
        $this->assertSame(8, $original['items'][0]['details'][0]['source_row']);
    }

    public function test_converting_a_previously_imported_document_does_not_allow_duplicates(): void
    {
        $project = Project::factory()->create();
        $service = app(ArchitectMeasurementSpreadsheet::class);
        $service->import($project, base_path('tests/Fixtures/architect-measurements.xls'), 'Original');

        try {
            $service->import($project, base_path('tests/Fixtures/architect-measurements.xlsx'), 'Convertido');
            $this->fail('Se esperaba un error por importación duplicada.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('ya se ha importado', $exception->errors()['file'][0]);
        }

        $this->assertSame(1, $project->workOrders()->count());
        $this->assertSame(149, $project->workOrderLines()->count());
    }

    public function test_reimporting_the_same_document_does_not_duplicate_any_records(): void
    {
        $project = Project::factory()->create();
        $service = app(ArchitectMeasurementSpreadsheet::class);
        $path = $this->workbook($this->validRows());
        $service->import($project, $path, 'Original');

        try {
            $service->import($project, $path, 'Otro nombre');
            $this->fail('Se esperaba un error por importación duplicada.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('ya se ha importado', $exception->errors()['file'][0]);
        }

        $this->assertSame(1, $project->workOrders()->count());
        $this->assertSame(1, $project->workOrderLines()->count());
        $this->assertSame(1, $project->projectChapters()->count());
    }

    public function test_same_document_can_be_imported_in_different_projects(): void
    {
        $first = Project::factory()->create();
        $second = Project::factory()->create();
        $path = $this->workbook($this->validRows());
        $service = app(ArchitectMeasurementSpreadsheet::class);

        $one = $service->import($first, $path, 'Primera obra');
        $two = $service->import($second, $path, 'Segunda obra');

        $this->assertNotSame($one->id, $two->id);
        $this->assertSame(1, $first->workOrderLines()->count());
        $this->assertSame(1, $second->workOrderLines()->count());
        $this->assertNotSame($one->workOrderLines()->sole()->project_chapter_id, $two->workOrderLines()->sole()->project_chapter_id);
    }

    /** @param list<list<mixed>> $rows */
    #[DataProvider('invalidDocuments')]
    public function test_invalid_documents_do_not_create_partial_records(array $rows): void
    {
        $project = Project::factory()->create();
        $path = $this->workbook($rows);

        try {
            app(ArchitectMeasurementSpreadsheet::class)->import($project, $path, 'No crear');
            $this->fail('Se esperaba un error de validación.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('file', $exception->errors());
        }

        $this->assertSame(0, WorkOrder::query()->count());
        $this->assertSame(0, WorkOrderLine::query()->count());
        $this->assertSame(0, ProjectChapter::query()->count());
        $this->assertSame(0, Unit::query()->count());
    }

    /** @return array<string, array{list<list<mixed>>}> */
    public static function invalidDocuments(): array
    {
        $header = ['codigo', 'Ud.', ' descripción', 'unidades', ' largo', ' ancho', ' alto ', ' parcial', 'medición', 'pre.uni.', ' importe'];
        $chapter = ['01#', '*', 'Capítulo'];
        $item = ['0101', 'm2', 'Partida'];
        $detail = ['', '', 'Zona', 1, 2, 1, 1, 2];
        $total = ['', '', 'TOTAL PARTIDA', '', '', '', '', '', 2, '', 0];

        return [
            'unrecognized header' => [[['código', 'nombre']]],
            'missing total' => [[$header, $chapter, $item, $detail]],
            'wrong sum' => [[$header, $chapter, $item, ['', '', 'Zona', 1, 3, 1, 1, 3], $total]],
            'missing numeric total' => [[$header, $chapter, $item, ['', '', 'TOTAL PARTIDA']]],
            'unsupported unit' => [[$header, $chapter, ['0101', 'inexistente', 'Partida'], $total]],
            'duplicate item' => [[$header, $chapter, $item, $total, $item, $total]],
            'duplicate chapter' => [[$header, $chapter, $item, $total, $chapter]],
            'item without chapter' => [[$header, $item, $total]],
            'formula without saved result' => [[$header, $chapter, $item, ['', '', 'Zona', 1, 2, 1, 1, '=1*2'], $total]],
            'negative overall quantity' => [[$header, $chapter, $item, ['', '', 'TOTAL PARTIDA', '', '', '', '', '', -2]]],
        ];
    }

    public function test_invalid_measurements_are_reported_on_the_upload_field(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();
        $path = $this->workbook([['cabecera incorrecta']]);
        $upload = UploadedFile::fake()->createWithContent('mediciones.xlsx', file_get_contents($path));

        Livewire::test(EditProject::class, ['record' => $project->id])
            ->callAction('importArchitectMeasurements', ['file' => $upload, 'name' => 'Mediciones'])
            ->assertHasActionErrors(['file'])->assertNotNotified();

        $this->assertSame(0, $project->workOrders()->count());
    }

    public function test_import_respects_creation_authorization(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();
        Gate::policy(WorkOrder::class, DenyArchitectWorkOrderCreation::class);

        Livewire::test(EditProject::class, ['record' => $project->id])
            ->assertActionHidden('importArchitectMeasurements');

        $this->assertSame(0, $project->workOrders()->count());
    }

    /** @return list<list<mixed>> */
    private function validRows(): array
    {
        return [
            ['codigo', 'Ud.', ' descripción', 'unidades', ' largo', ' ancho', ' alto ', ' parcial', 'medición', 'pre.uni.', ' importe'],
            ['01#', '*', 'ACTUACIONES PREVIAS'],
            ['0101', 'm2', 'PROTECCIÓN'],
            ['', '', 'Descripción larga'],
            ['', '', 'Zona', 1, 2, 1, 1, 2],
            ['', '', 'TOTAL PARTIDA', '', '', '', '', '', 2, 12.5, 25],
            ['', '', 'TOTAL CAPITULO 01#', '', '', '', '', '', '', '', 25],
        ];
    }

    /** @param list<list<mixed>> $rows */
    private function workbook(array $rows): string
    {
        Storage::fake('local');
        $path = Storage::disk('local')->path('architect.xlsx');
        $writer = new Writer;
        $writer->openToFile($path);
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return $path;
    }
}

class DenyArchitectWorkOrderCreation
{
    public function create(User $user): bool
    {
        return false;
    }
}
