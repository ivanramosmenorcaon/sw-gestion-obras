<?php

namespace App\Filament\Resources\WorkOrderPhases\RelationManagers;

use App\Filament\Resources\WorkOrderPhases\WorkOrderPhaseResource;
use App\Filament\Resources\WorkTimeEntries\WorkTimeEntryResource;
use App\Filament\Schemas\CostingForms;
use App\Support\BusinessRecordValidator;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class WorkTimeEntriesRelationManager extends RelationManager
{
    use HasResizableColumn;

    protected static string $relationship = 'workTimeEntries';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('costing.times');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return WorkOrderPhaseResource::canView($ownerRecord) && parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function form(Schema $schema): Schema
    {
        return CostingForms::configure($schema, 'time', ['work_order_id' => $this->getOwnerRecord()->work_order_id, 'work_order_phase_id' => $this->getOwnerRecord()->id]);
    }

    public function table(Table $table): Table
    {
        return WorkTimeEntryResource::table($table)->modelLabel(__('costing.time'))->pluralModelLabel(__('costing.times'))
            ->headerActions([CreateAction::make()->authorize(fn (): bool => WorkTimeEntryResource::canCreate() && WorkOrderPhaseResource::canEdit($this->getOwnerRecord()))->using(fn (array $data): Model => BusinessRecordValidator::inForm(fn (): Model => $this->getRelationship()->create(array_replace($data, ['work_order_id' => $this->getOwnerRecord()->work_order_id])), $this->getMountedActionSchema()?->getStatePath() ?? ''))])
            ->recordActions([ViewAction::make()->url(fn (Model $record): string => WorkTimeEntryResource::getUrl('view', ['record' => $record])), EditAction::make()->visible(fn (Model $record): bool => WorkTimeEntryResource::canEdit($record))->url(fn (Model $record): string => WorkTimeEntryResource::getUrl('edit', ['record' => $record]))]);
    }
}
