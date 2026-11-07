<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Filament\Resources\ProjectItems\ProjectItemResource;
use App\Filament\Schemas\ProjectItemForm;
use App\Support\BusinessRecordValidator;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ProjectItemsRelationManager extends RelationManager
{
    use HasResizableColumn;

    protected static string $relationship = 'projectItems';

    protected static ?string $title = 'Partidas de la obra';

    public function form(Schema $schema): Schema
    {
        return ProjectItemForm::configure($schema, ['project_id' => $this->getOwnerRecord()->id]);
    }

    public function table(Table $table): Table
    {
        return ProjectItemResource::table($table)
            ->headerActions([
                CreateAction::make()->authorize(fn (): bool => ProjectItemResource::canCreate())
                    ->using(fn (array $data): Model => BusinessRecordValidator::inForm(fn (): Model => $this->getRelationship()->create(array_replace($data, ['project_id' => $this->getOwnerRecord()->id])), $this->getMountedActionSchema()?->getStatePath() ?? '')),
            ]);
    }
}
