<?php

namespace App\Filament\Resources\WorkOrderEmployeeAssignments\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WorkOrderEmployeeAssignmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->dragReorderableColumns()
            ->stickableColumns()
            ->columns([
                TextColumn::make('workOrder.name')->searchable()->sortable()->label(__('business.fields.work_order_id'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('workOrderPhase.name')->searchable()->sortable()->label(__('business.fields.work_order_phase_id'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('employee.name')->searchable()->sortable()->label(__('business.fields.employee_id'))->toggleable(),
                TextColumn::make('role')->searchable()->sortable()->label(__('business.fields.role'))->toggleable(),
                TextColumn::make('status')->badge()->formatStateUsing(fn (?string $state): string => $state === null ? '' : __('business.options.'.$state))->label(__('business.fields.status'))->toggleable(),
                TextColumn::make('planned_start_date')->date('d/m/Y')->sortable()->label(__('business.fields.planned_start_date'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('planned_end_date')->date('d/m/Y')->sortable()->label(__('business.fields.planned_end_date'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('planned_hours')->numeric(decimalPlaces: 6, locale: 'es')->sortable()->label(__('business.fields.planned_hours'))->toggleable(),
                TextColumn::make('updated_at')->label(__('business.fields.updated_at'))->dateTime('d/m/Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])->filters([
                SelectFilter::make('status')->label(__('business.fields.status'))->options(['planned' => __('business.options.planned'), 'active' => __('business.options.active'), 'completed' => __('business.options.completed'), 'cancelled' => __('business.options.cancelled')]),
            ])->recordActions([EditAction::make()])->defaultSort('id', 'desc');
    }
}
