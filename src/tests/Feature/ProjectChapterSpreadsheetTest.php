<?php

namespace Tests\Feature;

use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\RelationManagers\ProjectChaptersRelationManager;
use App\Models\Project;
use App\Models\ProjectChapter;
use App\Models\User;
use App\Support\ProjectChapterSpreadsheet;
use Filament\Actions\Testing\TestAction;
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

class ProjectChapterSpreadsheetTest extends TestCase
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

    public function test_template_can_be_downloaded_and_imported_into_the_selected_project(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();
        $otherProject = Project::factory()->create();
        $path = Storage::disk('local')->path('template.xlsx');
        app(ProjectChapterSpreadsheet::class)->writeTemplate($path);

        Livewire::test(ProjectChaptersRelationManager::class, ['ownerRecord' => $project, 'pageClass' => EditProject::class])
            ->callAction(TestAction::make('downloadChapterTemplate')->table())
            ->assertFileDownloaded('plantilla-capitulos.xlsx');

        $upload = UploadedFile::fake()->createWithContent('template.xlsx', file_get_contents($path));
        Livewire::test(ProjectChaptersRelationManager::class, ['ownerRecord' => $project, 'pageClass' => EditProject::class])
            ->callAction(TestAction::make('importChapters')->table(), ['file' => $upload])
            ->assertHasNoActionErrors()
            ->assertNotified('Capítulos importados');

        $this->assertSame(5, $project->projectChapters()->count());
        $this->assertSame(0, $otherProject->projectChapters()->count());
        $this->assertDatabaseHas('project_chapters', ['project_id' => $project->id, 'code' => '01', 'name' => 'Demoliciones']);
    }

    public function test_import_supports_children_before_parents_and_preserves_existing_chapters(): void
    {
        $project = Project::factory()->create();
        $existing = ProjectChapter::factory()->for($project)->create(['code' => '01', 'name' => 'Nombre propio']);
        $path = $this->spreadsheet([
            ['02.01', 'Iluminación', '02', '', 1, 1],
            ['02', 'Electricidad', '', 'Instalaciones', 2, 1],
            ['01', 'No reemplazar', '', '', 1, 0],
        ]);
        $service = app(ProjectChapterSpreadsheet::class);

        $this->assertSame(['created' => 2, 'skipped' => 1], $service->import($project, $path));
        $this->assertSame(['created' => 0, 'skipped' => 3], $service->import($project, $path));

        $parent = $project->projectChapters()->where('code', '02')->sole();
        $this->assertDatabaseHas('project_chapters', ['project_id' => $project->id, 'code' => '02.01', 'parent_id' => $parent->id]);
        $this->assertSame('Nombre propio', $existing->fresh()->name);
        $this->assertTrue($existing->fresh()->is_active);
        $this->assertSame(3, $project->projectChapters()->count());
    }

    /** @param list<list<string|int>> $rows */
    #[DataProvider('invalidRows')]
    public function test_invalid_imports_do_not_save_partial_results(array $rows): void
    {
        $project = Project::factory()->create();
        $path = $this->spreadsheet($rows);

        try {
            app(ProjectChapterSpreadsheet::class)->import($project, $path);
            $this->fail('Se esperaba un error de validación.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('file', $exception->errors());
        }

        $this->assertSame(0, $project->projectChapters()->count());
    }

    /** @return array<string, array{list<list<string|int>>}> */
    public static function invalidRows(): array
    {
        return [
            'missing name' => [[['01', '', '', '', 1, 1]]],
            'invalid position' => [[['01', 'Demoliciones', '', '', 0, 1]]],
            'invalid active' => [[['01', 'Demoliciones', '', '', 1, 'sí']]],
            'duplicate code' => [[['01', 'Uno', '', '', 1, 1], ['01', 'Dos', '', '', 2, 1]]],
            'missing parent rolls back earlier row' => [[['01', 'Uno', '', '', 1, 1], ['02', 'Dos', '99', '', 2, 1]]],
            'cycle' => [[['01', 'Uno', '02', '', 1, 1], ['02', 'Dos', '01', '', 2, 1]]],
            'self parent' => [[['01', 'Uno', '01', '', 1, 1]]],
            'formula' => [[['01', '=1+1', '', '', 1, 1]]],
            'empty workbook' => [[]],
        ];
    }

    public function test_parent_codes_from_other_projects_are_rejected(): void
    {
        $project = Project::factory()->create();
        ProjectChapter::factory()->create(['code' => '99']);
        $path = $this->spreadsheet([['01', 'Uno', '99', '', 1, 1]]);

        $this->expectException(ValidationException::class);
        app(ProjectChapterSpreadsheet::class)->import($project, $path);
    }

    public function test_validation_errors_are_shown_on_the_upload_field(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();
        $path = $this->spreadsheet([['01', 'Uno', '99', '', 1, 1]]);
        $upload = UploadedFile::fake()->createWithContent('chapters.xlsx', file_get_contents($path));

        Livewire::test(ProjectChaptersRelationManager::class, ['ownerRecord' => $project, 'pageClass' => EditProject::class])
            ->callAction(TestAction::make('importChapters')->table(), ['file' => $upload])
            ->assertHasActionErrors(['file'])
            ->assertNotNotified();

        $this->assertSame(0, $project->projectChapters()->count());
    }

    public function test_invalid_workbooks_are_rejected_without_saving_chapters(): void
    {
        Storage::fake('local');
        $project = Project::factory()->create();
        Storage::disk('local')->put('broken.xlsx', 'This is not an Excel workbook');

        $this->expectException(ValidationException::class);
        app(ProjectChapterSpreadsheet::class)->import($project, Storage::disk('local')->path('broken.xlsx'));
    }

    public function test_import_is_unavailable_when_editing_the_project_is_denied(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();
        Gate::policy(Project::class, DenyChapterImportProjectUpdate::class);

        Livewire::test(ProjectChaptersRelationManager::class, ['ownerRecord' => $project, 'pageClass' => EditProject::class])
            ->assertActionHidden(TestAction::make('importChapters')->table());

        $this->assertSame(0, $project->projectChapters()->count());
    }

    public function test_import_is_unavailable_when_creation_is_denied(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();
        Gate::policy(ProjectChapter::class, DenyChapterImportCreation::class);

        Livewire::test(ProjectChaptersRelationManager::class, ['ownerRecord' => $project, 'pageClass' => EditProject::class])
            ->assertActionHidden(TestAction::make('importChapters')->table());

        $this->assertSame(0, $project->projectChapters()->count());
    }

    /** @param list<list<string|int>> $rows */
    private function spreadsheet(array $rows): string
    {
        Storage::fake('local');
        $path = Storage::disk('local')->path('chapters.xlsx');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['Código', 'Nombre', 'Código padre', 'Descripción', 'Posición', 'Activo']));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return $path;
    }
}

class DenyChapterImportCreation
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }
}

class DenyChapterImportProjectUpdate
{
    public function update(User $user, Project $project): bool
    {
        return false;
    }
}
