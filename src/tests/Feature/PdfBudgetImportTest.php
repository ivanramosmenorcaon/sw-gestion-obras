<?php

namespace Tests\Feature;

use App\Filament\Resources\Projects\Pages\EditProject;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\BudgetEditor;
use App\Support\PdfBudgetImporter;
use App\Support\PdfBudgetReader;
use App\Support\WorkOrderChapterTree;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PdfBudgetImportTest extends TestCase
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

    #[DataProvider('documents')]
    public function test_original_pdf_is_read_and_imported_without_summary_or_tax_rows_becoming_items(string $fixture, int $chapters, int $items, string $net): void
    {
        $project = Project::factory()->create();
        $service = app(PdfBudgetImporter::class);
        $path = base_path('tests/Fixtures/'.$fixture);

        $document = $service->inspect($path);

        $this->assertCount($chapters, $document['chapters']);
        $this->assertCount($items, $document['items']);
        $this->assertSame($net, $document['net_total']);

        $order = $service->import($project, $path, 'Presupuesto PDF');

        $this->assertSame('draft', $order->status);
        $this->assertSame($chapters, $project->projectChapters()->count());
        $this->assertSame($items, $order->workOrderLines()->count());
        $this->assertStringContainsString('sin impuestos', $order->instructions);
        $this->assertSame($net, BudgetEditor::total(app(WorkOrderChapterTree::class)->snapshots($order)));
    }

    public function test_upload_previews_totals_before_creating_the_order(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();
        $service = app(PdfBudgetImporter::class);
        $path = base_path('tests/Fixtures/budget-listado.pdf');
        $preview = $service->inspect($path);
        $this->assertCount(23, $preview['chapters']);
        $this->assertCount(145, $preview['items']);
        $this->assertSame('160489.06', $preview['net_total']);
        $this->assertSame(0, $project->workOrders()->count());

        $order = $service->import($project, $path, 'Oferta PR-293');
        $this->assertStringContainsString('IVA', $order->instructions);
        $this->assertStringContainsString('33.702,70', $order->instructions);
        $this->assertSame(145, $order->workOrderLines()->count());
    }

    public function test_repeated_pdf_is_not_duplicated_in_the_project(): void
    {
        $project = Project::factory()->create();
        $service = app(PdfBudgetImporter::class);
        $path = base_path('tests/Fixtures/budget-compacto.pdf');
        $service->import($project, $path, 'Original');

        try {
            $service->import($project, $path, 'Otra copia');
            $this->fail('Se esperaba un error por importación duplicada.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('ya se ha importado', $exception->errors()['file'][0]);
        }

        $this->assertSame(1, $project->workOrders()->count());
        $this->assertSame(90, $project->workOrderLines()->count());
    }

    public function test_inconsistent_amounts_are_rejected_without_creating_any_records(): void
    {
        $project = Project::factory()->create();
        $path = base_path('tests/Fixtures/budget-listado.pdf');
        $pages = app(PdfBudgetReader::class)->pages($path);
        foreach ($pages[0]['rows'] as &$row) {
            if (str_starts_with($row['text'], 'TOTAL PARTIDA')) {
                $row['text'] = str_replace('109,80', '999,99', $row['text']);
                foreach ($row['spans'] as &$span) {
                    $span['text'] = str_replace('109,80', '999,99', $span['text']);
                }
                unset($span);
                break;
            }
        }
        unset($row);
        $path = $this->pdfWithRows($pages[0]['rows']);

        try {
            app(PdfBudgetImporter::class)->import($project, $path, 'No importar');
            $this->fail('Se esperaba que los importes se comprobaran.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('importe impreso', $exception->errors()['file'][0]);
        }

        $this->assertSame(0, $project->workOrders()->count());
        $this->assertSame(0, $project->projectChapters()->count());
    }

    public function test_scanned_or_unrecognized_layouts_are_rejected(): void
    {
        $path = $this->pdfWithRows([]);

        $this->expectException(ValidationException::class);
        app(PdfBudgetImporter::class)->inspect($path);
    }

    public function test_malformed_pdf_produces_a_validation_error(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('broken.pdf', '%PDF-1.7 incomplete');

        $this->expectException(ValidationException::class);
        app(PdfBudgetReader::class)->pages(Storage::disk('local')->path('broken.pdf'));
    }

    public function test_import_action_requires_permission_to_create_work_orders(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();
        Gate::policy(WorkOrder::class, DenyPdfWorkOrderCreation::class);

        Livewire::test(EditProject::class, ['record' => $project->id])->assertActionDoesNotExist('importPdfBudget');

        $this->assertSame(0, $project->workOrders()->count());
    }

    /** @return array<string, array{string, int, int, string}> */
    public static function documents(): array
    {
        return [
            'listado con resumen e IVA' => ['budget-listado.pdf', 23, 145, '160489.06'],
            'presupuesto compacto' => ['budget-compacto.pdf', 16, 90, '101855.40'],
        ];
    }

    /** @param list<array{y: float, text: string, spans: list<array{x: float, text: string, font: float}>}> $rows */
    private function pdfWithRows(array $rows): string
    {
        $stream = '';
        foreach ($rows as $row) {
            foreach ($row['spans'] as $span) {
                $text = mb_convert_encoding($span['text'], 'Windows-1252', 'UTF-8');
                $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
                $stream .= sprintf("BT /F1 8 Tf 1 0 0 1 %.4F %.4F Tm (%s) Tj ET\n", $span['x'], $row['y'], $text);
            }
        }
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 600 850] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Length '.strlen($stream).">>\nstream\n".$stream.'endstream',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
        Storage::fake('local');
        Storage::disk('local')->put('test.pdf', $pdf);

        return Storage::disk('local')->path('test.pdf');
    }
}

class DenyPdfWorkOrderCreation
{
    public function create(User $user): bool
    {
        return false;
    }
}
