<?php

namespace App\Filament\Resources\WorkOrders\Tables;

use App\Filament\Resources\Budgets\BudgetResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\WorkOrder;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WorkOrdersTable
{
    public static function budgetAction(): Action
    {
        return Action::make('prepareBudget')->label('Crear presupuesto')->icon('heroicon-o-document-text')
            ->authorize(fn (WorkOrder $record): bool => WorkOrderResource::canView($record) && BudgetResource::canCreate())
            ->url(fn (WorkOrder $record): string => WorkOrderResource::getUrl('tree', ['record' => $record]));
    }

    public static function treeAction(): Action
    {
        return Action::make('chapterTree')->label('Árbol y presupuesto')->icon('heroicon-o-folder-open')
            ->authorize(fn (WorkOrder $record): bool => WorkOrderResource::canView($record))
            ->url(fn (WorkOrder $record): string => WorkOrderResource::getUrl('tree', ['record' => $record]));
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->dragReorderableColumns()
            ->stickableColumns()
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withExists(['workOrderPhases', 'workOrderLines', 'workOrderEmployeeAssignments', 'workOrderContractorAssignments']))->columns([
                TextColumn::make('project.name')->searchable()->sortable()->label(__('business.fields.project_id'))->toggleable(),
                TextColumn::make('reference')->searchable()->sortable()->label(__('business.fields.reference'))->toggleable(),
                TextColumn::make('name')->searchable()->sortable()->label(__('business.fields.name'))->toggleable(),
                TextColumn::make('supervisor.name')->searchable()->sortable()->label(__('business.fields.supervisor_id'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')->badge()->formatStateUsing(fn (?string $state): string => $state === null ? '' : __('business.options.'.$state))->label(__('business.fields.status'))->toggleable(),
                TextColumn::make('priority')->badge()->formatStateUsing(fn (?string $state): string => $state === null ? '' : __('business.options.'.$state))->label(__('business.fields.priority'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('planned_start_date')->date('d/m/Y')->sortable()->label(__('business.fields.planned_start_date'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('planned_end_date')->date('d/m/Y')->sortable()->label(__('business.fields.planned_end_date'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('actual_start_date')->date('d/m/Y')->sortable()->label(__('business.fields.actual_start_date'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('actual_end_date')->date('d/m/Y')->sortable()->label(__('business.fields.actual_end_date'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->label(__('business.fields.updated_at'))->dateTime('d/m/Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])->filters([
                SelectFilter::make('status')->label(__('business.fields.status'))->options(['draft' => __('business.options.draft'), 'planned' => __('business.options.planned'), 'in_progress' => __('business.options.in_progress'), 'on_hold' => __('business.options.on_hold'), 'completed' => __('business.options.completed'), 'cancelled' => __('business.options.cancelled')]),
                SelectFilter::make('priority')->label(__('business.fields.priority'))->options(['low' => __('business.options.low'), 'normal' => __('business.options.normal'), 'high' => __('business.options.high'), 'urgent' => __('business.options.urgent')]),
            ])->recordActions([
                self::treeAction(),
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('Se eliminará la orden y, si está en borrador, sus partidas, fases y asignaciones. Los capítulos de la obra se conservarán.'),
            ])->defaultSort('id', 'desc');
    }
}
