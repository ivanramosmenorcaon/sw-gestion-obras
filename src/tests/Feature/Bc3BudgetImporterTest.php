<?php

namespace Tests\Feature;

use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\WorkOrderLines\Pages\EditWorkOrderLine;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderLine;
use App\Support\Bc3BudgetImporter;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class Bc3BudgetImporterTest extends TestCase
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

    public function test_original_bc3_imports_all_budget_items_and_preserves_measurements_and_resources(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();
        $other = Project::factory()->create();
        $upload = UploadedFile::fake()->createWithContent('PRESUPUESTO.BC3', file_get_contents(base_path('tests/Fixtures/architect-budget.bc3')));

        $page = Livewire::test(EditProject::class, ['record' => $project->id])
            ->callAction('importBc3Budget', ['file' => $upload, 'name' => 'Presupuesto Rovellada'])
            ->assertHasNoActionErrors()->assertNotified('Presupuesto BC3 importado');

        $order = $project->workOrders()->sole();
        $this->assertSame('draft', $order->status);
        $this->assertSame(23, $project->projectChapters()->count());
        $this->assertSame(149, $order->workOrderLines()->count());
        $this->assertSame(361, $order->workOrderLines()->get()->sum(fn (WorkOrderLine $line): int => count($line->measurement_details)));
        $this->assertSame(0, $other->workOrders()->count());
        $this->assertSame(0, $other->projectChapters()->count());
        $line = $order->workOrderLines()->where('source_code', 'D0104.0010')->sole();
        $this->assertSame('16.244000', $line->planned_quantity);
        $this->assertSame('0.000000', $line->estimated_unit_sale_price);
        $this->assertSame('D01', $line->projectChapter->code);
        $this->assertStringContainsString('marés', $line->description);
        $this->assertStringContainsString('0207', $line->notes);
        $this->assertNotEmpty($line->budget_breakdown);
        $this->assertTrue(collect($line->measurement_details)->contains(fn (array $row): bool => ($row['subtotal'] ?? 0) < 0));
        Livewire::test(EditWorkOrderLine::class, ['record' => $line->id])
            ->assertSuccessful()->assertSee('Descompuestos originales del BC3');
    }

    public function test_same_bc3_cannot_be_imported_twice_in_one_project(): void
    {
        $project = Project::factory()->create();
        $service = app(Bc3BudgetImporter::class);
        $path = $this->file($this->budget());
        $service->import($project, $path, 'Original');

        try {
            $service->import($project, $path, 'Duplicado');
            $this->fail('Se esperaba un error por presupuesto duplicado.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('ya se ha importado', $exception->errors()['file'][0]);
        }

        $this->assertSame(1, $project->workOrders()->count());
        $this->assertSame(1, $project->workOrderLines()->count());
    }

    public function test_nested_chapters_prices_and_negative_measurements_are_preserved(): void
    {
        $project = Project::factory()->create();
        $path = $this->file($this->budget());

        $order = app(Bc3BudgetImporter::class)->import($project, $path, 'Presupuesto');

        $line = $order->workOrderLines()->sole();
        $this->assertSame('12.500000', $line->estimated_unit_sale_price);
        $this->assertSame('3.000000', $line->planned_quantity);
        $this->assertSame(-1.0, (float) $line->measurement_details[1]['subtotal']);
        $this->assertSame('SUB', $line->projectChapter->code);
        $this->assertSame('CAP', $line->projectChapter->parent->code);
        $this->assertSame('R1', $line->budget_breakdown[0]['code']);
        $this->assertSame(2.5, $line->budget_breakdown[0]['price']);
        $this->assertSame(0.5, $line->budget_breakdown[0]['quantity']);
    }

    #[DataProvider('invalidBudgets')]
    public function test_invalid_budgets_do_not_create_partial_records(string $search, string $replacement): void
    {
        $project = Project::factory()->create();
        $path = $this->file(str_replace($search, $replacement, $this->budget()));

        try {
            app(Bc3BudgetImporter::class)->import($project, $path, 'No crear');
            $this->fail('Se esperaba un error de validación.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('file', $exception->errors());
        }

        $this->assertSame(0, $project->workOrders()->count());
        $this->assertSame(0, $project->workOrderLines()->count());
        $this->assertSame(0, $project->projectChapters()->count());
    }

    /** @return array<string, array{string, string}> */
    public static function invalidBudgets(): array
    {
        return [
            'undefined resource' => ['~D|P1|R1', '~D|P1|MISSING'],
            'cyclic chapter' => ['~D|CAP#|SUB#', '~D|CAP#|CAP#'],
            'cyclic breakdown' => ['~D|P1|R1', '~D|P1|P1'],
            'measurement mismatch' => ['|1\\1\\1\\|3|', '|1\\1\\1\\|9|'],
            'missing numeric total' => ['|1\\1\\1\\|3|', '|1\\1\\1\\||'],
            'unknown item unit rolls back chapters' => ['~C|P1|m2|', '~C|P1|UNKNOWN|'],
            'wrong currency' => ['EUR', 'USD'],
            'global coefficients' => ['|0\\\\\\\\|', '|10\\\\\\\\|'],
            'multiple prices' => ['|12.50|', '|12.50\\20|'],
            'negative price' => ['|12.50|', '|-12.50|'],
            'measurement formula' => ['|\\Zona\\1\\4', '|3\\Zona\\1\\4'],
            'malformed decomposition' => ['~D|P1|R1\\\\0.5\\|', '~D|P1|R1\\|'],
            'missing root' => ['~C|ROOT##|', '~C|ROOT|'],
        ];
    }

    public function test_identical_document_can_be_imported_in_another_project(): void
    {
        $one = Project::factory()->create();
        $two = Project::factory()->create();
        $service = app(Bc3BudgetImporter::class);
        $path = $this->file($this->budget());

        $first = $service->import($one, $path, 'Primero');
        $second = $service->import($two, $path, 'Segundo');

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame($one->id, $first->workOrderLines()->sole()->project_id);
        $this->assertSame($two->id, $second->workOrderLines()->sole()->project_id);
    }

    #[DataProvider('encodings')]
    public function test_supported_encodings_preserve_spanish_characters(string $encoding, string $header): void
    {
        $text = str_replace('|ANSI|', '|'.$header.'|', $this->budget());
        $path = $this->file(mb_convert_encoding($text, $encoding, 'UTF-8'));

        $document = app(Bc3BudgetImporter::class)->inspect($path);

        $this->assertSame('Albañilería', $document['chapters'][0]['name']);
        $this->assertSame('Descripción de la partida', $document['items'][0]['description']);
    }

    /** @return array<string, array{string, string}> */
    public static function encodings(): array
    {
        return ['ANSI' => ['Windows-1252', 'ANSI'], 'UTF8' => ['UTF-8', 'UTF-8'], 'OEM' => ['CP850', '850']];
    }

    public function test_validation_errors_are_reported_on_the_upload_field(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();
        $upload = UploadedFile::fake()->createWithContent('invalid.bc3', 'No es un presupuesto');

        Livewire::test(EditProject::class, ['record' => $project->id])
            ->callAction('importBc3Budget', ['file' => $upload, 'name' => 'No importar'])
            ->assertHasActionErrors(['file'])->assertNotNotified();

        $this->assertSame(0, $project->workOrders()->count());
    }

    public function test_import_respects_creation_authorization(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();
        Gate::policy(WorkOrder::class, DenyBc3WorkOrderCreation::class);

        Livewire::test(EditProject::class, ['record' => $project->id])->assertActionHidden('importBc3Budget');

        $this->assertSame(0, $project->workOrders()->count());
    }

    private function budget(): string
    {
        return <<<'BC3'
~V|Test|FIEBDC-3/2020|Test||ANSI||2||||
~K|2\3\3\3\2\2\2\2\EUR\|0\\\\|
~C|ROOT##||Obra|0||0|
~D|ROOT##|CAP#\\1\|
~C|CAP#|*|Albañilería|0||0|
~D|CAP#|SUB#\\1\|
~C|SUB#|*|Subcapítulo|0||0|
~D|SUB#|P1\\3\|
~C|P1|m2|Partida|12.50||0|
~T|P1|Descripción de la partida|
~D|P1|R1\\0.5\|
~C|R1|h|Oficial|2.50||1|
~M|SUB#\P1|1\1\1\|3|\Zona\1\4\\\\Descuento\-1\1\\\|01.01|
BC3;
    }

    private function file(string $text): string
    {
        Storage::fake('local');
        Storage::disk('local')->put('budget.bc3', $text);

        return Storage::disk('local')->path('budget.bc3');
    }
}

class DenyBc3WorkOrderCreation
{
    public function create(User $user): bool
    {
        return false;
    }
}
