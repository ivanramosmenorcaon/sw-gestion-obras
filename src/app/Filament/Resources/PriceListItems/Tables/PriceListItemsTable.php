<?php

namespace App\Filament\Resources\PriceListItems\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PriceListItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('priceList.name')->searchable()->sortable()->label(__('business.fields.price_list_id'))->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('product.name')->searchable()->sortable()->label(__('business.fields.product_id'))->toggleable(),
            TextColumn::make('productUnit.name')->searchable()->sortable()->label(__('business.fields.product_unit_id'))->toggleable(),
            TextColumn::make('minimum_quantity')->numeric(decimalPlaces: 6, locale: 'es')->sortable()->label(__('business.fields.minimum_quantity'))->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('unit_price')->numeric(decimalPlaces: 6, locale: 'es')->sortable()->label(__('business.fields.unit_price'))->toggleable(),
            TextColumn::make('valid_from')->date('d/m/Y')->sortable()->label(__('business.fields.valid_from'))->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('valid_until')->date('d/m/Y')->sortable()->label(__('business.fields.valid_until'))->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')->label(__('business.fields.updated_at'))->dateTime('d/m/Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
        ])->recordActions([EditAction::make()])->defaultSort('id', 'desc');
    }
}
