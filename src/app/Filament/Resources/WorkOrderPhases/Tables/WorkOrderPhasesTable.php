<?php

namespace App\Filament\Resources\WorkOrderPhases\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WorkOrderPhasesTable
{
    public static function configure(Table $table): Table
    {
        return $table->modifyQueryUsing(fn (Builder $query): Builder => $query->withExists(['workOrderLines', 'workOrderEmployeeAssignments', 'workOrderContractorAssignments']))->columns([
            TextColumn::make('workOrder.name')->searchable()->sortable()->label(__('business.fields.work_order_id'))->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('code')->searchable()->sortable()->label(__('business.fields.code'))->toggleable(),
            TextColumn::make('name')->searchable()->sortable()->label(__('business.fields.name'))->toggleable(),
            TextColumn::make('position')->searchable()->sortable()->label(__('business.fields.position'))->toggleable(),
            TextColumn::make('status')->badge()->formatStateUsing(fn (?string $state): string => $state === null ? '' : __('business.options.'.$state))->label(__('business.fields.status'))->toggleable(),
            TextColumn::make('planned_start_date')->date('d/m/Y')->sortable()->label(__('business.fields.planned_start_date'))->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('planned_end_date')->date('d/m/Y')->sortable()->label(__('business.fields.planned_end_date'))->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('actual_start_date')->date('d/m/Y')->sortable()->label(__('business.fields.actual_start_date'))->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('actual_end_date')->date('d/m/Y')->sortable()->label(__('business.fields.actual_end_date'))->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')->label(__('business.fields.updated_at'))->dateTime('d/m/Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            SelectFilter::make('status')->label(__('business.fields.status'))->options(['pending' => __('business.options.pending'), 'in_progress' => __('business.options.in_progress'), 'on_hold' => __('business.options.on_hold'), 'completed' => __('business.options.completed'), 'cancelled' => __('business.options.cancelled')]),
        ])->recordActions([EditAction::make()])->defaultSort('id', 'desc');
    }
}
