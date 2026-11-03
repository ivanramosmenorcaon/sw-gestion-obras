<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Filament\Resources\ProjectChapters\Schemas\ProjectChapterForm;
use App\Filament\Resources\ProjectChapters\Tables\ProjectChaptersTable;
use App\Support\BusinessRecordValidator;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ProjectChaptersRelationManager extends RelationManager
{
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
