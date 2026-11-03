<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Filament\Resources\ProjectChapters\Schemas\ProjectChapterForm;
use App\Filament\Resources\ProjectChapters\Tables\ProjectChaptersTable;
use App\Filament\Resources\Projects\ProjectResource;
use App\Support\BusinessRecordValidator;
use App\Support\ProjectChapterSpreadsheet;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectChaptersRelationManager extends RelationManager
{
    use HasResizableColumn;

    protected static string $relationship = 'projectChapters';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('business.models.project_chapters.plural');
    }

    public function form(Schema $schema): Schema
    {
        return ProjectChapterForm::configure($schema, ['project_id' => $this->getOwnerRecord()->getKey()]);
    }

    public function table(Table $table): Table
    {
        return ProjectChaptersTable::configure($table)
            ->modelLabel(__('business.models.project_chapters.singular'))
            ->pluralModelLabel(__('business.models.project_chapters.plural'))
            ->headerActions([
                Action::make('downloadChapterTemplate')
                    ->label('Descargar plantilla Excel')
                    ->action(fn (): StreamedResponse => app(ProjectChapterSpreadsheet::class)->download()),
                Action::make('importChapters')
                    ->label('Importar plantilla de capítulos')
                    ->authorize(fn (): bool => $this->canCreate() && ProjectResource::canEdit($this->getOwnerRecord()))
                    ->modalHeading('Importar plantilla de capítulos')
                    ->modalDescription('Se añadirán los códigos nuevos a esta obra. Los capítulos existentes se conservarán sin cambios. Si hay errores no se guardará ningún capítulo.')
                    ->modalSubmitActionLabel('Importar')
                    ->schema([
                        FileUpload::make('file')
                            ->label('Plantilla Excel')
                            ->helperText('Solo la plantilla descargable de capítulos (.xlsx, hasta 2 MB y 1000 capítulos). Para el Excel del arquitecto, utilice «Importar mediciones» en la cabecera de la obra.')
                            ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                            ->maxSize(2048)
                            ->storeFiles(false)
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $result = BusinessRecordValidator::inForm(
                            fn (): array => app(ProjectChapterSpreadsheet::class)->import($this->getOwnerRecord(), $data['file']->getRealPath()),
                            $this->getMountedActionSchema()?->getStatePath() ?? '',
                        );
                        $this->resetTable();
                        Notification::make()->success()->title('Capítulos importados')
                            ->body($result['created'].' creados y '.$result['skipped'].' omitidos porque ya existían.')
                            ->send();
                    }),
                CreateAction::make()->using(fn (array $data): Model => BusinessRecordValidator::inForm(fn (): Model => $this->getRelationship()->create($data), $this->getMountedActionSchema()?->getStatePath() ?? '')),
            ])
            ->recordActions([
                EditAction::make()->using(function (Model $record, array $data): Model {
                    return BusinessRecordValidator::inForm(function () use ($record, $data): Model {
                        $record->update($data);

                        return $record;
                    }, $this->getMountedActionSchema()?->getStatePath() ?? '');
                }),
                DeleteAction::make()->visible(fn (Model $record): bool => BusinessRecordValidator::canDelete($record)),
            ]);
    }
}
