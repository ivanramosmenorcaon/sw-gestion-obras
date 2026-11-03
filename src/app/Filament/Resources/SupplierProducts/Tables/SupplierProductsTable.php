<?php

namespace App\Filament\Resources\SupplierProducts\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class SupplierProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->dragReorderableColumns()
            ->stickableColumns()
            ->columns([
                TextColumn::make('supplier.legal_name')->searchable()->sortable()->label(__('business.fields.supplier_id'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('product.name')->searchable()->sortable()->label(__('business.fields.product_id'))->toggleable(),
                TextColumn::make('productUnit.name')->searchable()->sortable()->label(__('business.fields.product_unit_id'))->toggleable(),
                TextColumn::make('supplier_reference')->searchable()->sortable()->label(__('business.fields.supplier_reference'))->toggleable(),
                TextColumn::make('minimum_order_quantity')->numeric(decimalPlaces: 6, locale: 'es')->sortable()->label(__('business.fields.minimum_order_quantity'))->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')->boolean()->label(__('business.fields.is_active'))->toggleable(),
                TextColumn::make('updated_at')->label(__('business.fields.updated_at'))->dateTime('d/m/Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])->filters([
                TernaryFilter::make('is_active')->label(__('business.fields.is_active')),
            ])->recordActions([EditAction::make()])->defaultSort('id', 'desc');
    }
}
