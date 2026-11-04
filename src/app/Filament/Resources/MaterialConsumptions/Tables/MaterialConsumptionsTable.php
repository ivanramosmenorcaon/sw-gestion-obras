<?php

namespace App\Filament\Resources\MaterialConsumptions\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MaterialConsumptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->columns([TextColumn::make('workOrder.reference')->label(__('costing.fields.work_order_id')), TextColumn::make('workOrderPhase.name')->label(__('costing.fields.work_order_phase_id')), TextColumn::make('purchaseDeliveryLine.description')->label(__('costing.fields.description'))->searchable(), TextColumn::make('quantity')->label(__('costing.fields.quantity')), TextColumn::make('consumed_date')->label(__('costing.fields.consumed_date')), TextColumn::make('status')->label(__('costing.fields.status'))->formatStateUsing(fn (string $state): string => __('costing.options.'.$state))->badge(), TextColumn::make('total_cost')->label(__('costing.fields.total_cost'))->money('EUR')])->recordActions([ViewAction::make(), EditAction::make()])->dragReorderableColumns()->stickableColumns();
    }
}
