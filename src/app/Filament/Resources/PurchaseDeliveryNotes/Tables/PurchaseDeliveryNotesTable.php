<?php

namespace App\Filament\Resources\PurchaseDeliveryNotes\Tables;

use App\Filament\Support\ActionIconsHelper;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PurchaseDeliveryNotesTable
{
    public static function configure(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->columns([TextColumn::make('reference')->label(__('costing.fields.reference'))->searchable(), TextColumn::make('supplier.legal_name')->label(__('costing.fields.supplier_id')), TextColumn::make('warehouse.name')->label(__('costing.fields.warehouse_id')), TextColumn::make('received_date')->label(__('costing.fields.received_date')), TextColumn::make('status')->label(__('costing.fields.status'))->formatStateUsing(fn (string $state): string => __('costing.options.'.$state))->badge(), TextColumn::make('net_total')->label(__('costing.fields.net_total'))->money('EUR')])->recordActions(ActionIconsHelper::configureActions([ViewAction::make(), EditAction::make()]))->dragReorderableColumns()->stickableColumns();
    }
}
