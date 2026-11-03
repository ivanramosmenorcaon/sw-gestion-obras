<?php

namespace App\Filament\Resources\WorkOrders\RelationManagers;

use App\Filament\Resources\WorkOrderLines\Schemas\WorkOrderLineForm;
use App\Filament\Resources\WorkOrderLines\Tables\WorkOrderLinesTable;
use App\Support\BusinessRecordValidator;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class WorkOrderLinesRelationManager extends RelationManager
{
    use HasResizableColumn;

    protected static string $relationship = 'workOrderLines';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('business.models.work_order_lines.plural');
    }

    public function form(Schema $schema): Schema
    {
        return WorkOrderLineForm::configure($schema, ['work_order_id' => $this->getOwnerRecord()->getKey(), 'project_id' => $this->getOwnerRecord()->project_id]);
    }

    public function table(Table $table): Table
    {
        return WorkOrderLinesTable::configure($table)
            ->modelLabel(__('business.models.work_order_lines.singular'))
            ->pluralModelLabel(__('business.models.work_order_lines.plural'))
            ->headerActions([
                CreateAction::make()->using(fn (array $data): Model => BusinessRecordValidator::inForm(fn (): Model => $this->getRelationship()->create($data), $this->getMountedActionSchema()?->getStatePath() ?? '')),
            ])
            ->recordActions([
                WorkOrderLinesTable::breakdownAction(),
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
