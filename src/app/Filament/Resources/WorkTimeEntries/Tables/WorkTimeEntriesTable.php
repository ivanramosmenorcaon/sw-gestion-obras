<?php

namespace App\Filament\Resources\WorkTimeEntries\Tables;

use App\Support\OperationalAccess;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WorkTimeEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->columns([TextColumn::make('workOrder.reference')->label(__('costing.fields.work_order_id')), TextColumn::make('workOrderPhase.name')->label(__('costing.fields.work_order_phase_id')), TextColumn::make('employee.name')->label(__('costing.fields.employee_id')), TextColumn::make('contractor.legal_name')->label(__('costing.fields.contractor_id')), TextColumn::make('started_at')->label(__('costing.fields.started_at')), TextColumn::make('ended_at')->label(__('costing.fields.ended_at')), TextColumn::make('hours')->label(__('costing.fields.hours')), TextColumn::make('total_cost')->visible(fn (): bool => OperationalAccess::canSeeCosts())->label(__('costing.fields.total_cost'))->money('EUR'), TextColumn::make('status')->label(__('costing.fields.status'))->formatStateUsing(fn (string $state): string => __('costing.options.'.$state))->badge()])->recordActions([ViewAction::make(), EditAction::make()])->dragReorderableColumns()->stickableColumns();
    }
}
