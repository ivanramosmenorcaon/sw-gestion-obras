<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Support\PdfBudgetImporter;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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
    }

    /** @return array<string, array{string, int, int, string}> */
    public static function documents(): array
    {
        return [
            'listado con resumen e IVA' => ['budget-listado.pdf', 23, 145, '160489.06'],
            'presupuesto compacto' => ['budget-compacto.pdf', 16, 90, '101855.40'],
        ];
    }
}
