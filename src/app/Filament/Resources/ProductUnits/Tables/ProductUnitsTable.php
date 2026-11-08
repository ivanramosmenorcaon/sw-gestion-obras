<?php

namespace App\Filament\Resources\ProductUnits\Tables;

use App\Filament\Support\ActionIconsHelper;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductUnitsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->dragReorderableColumns()
            ->stickableColumns()
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withExists(['workOrderLines', 'supplierProducts', 'priceListItems']))->columns([
                TextColumn::make('product.name')->searchable()->sortable()->label(__('business.fields.product_id'))->toggleable(),
                TextColumn::make('code')->searchable()->sortable()->label(__('business.fields.code'))->toggleable(),
                TextColumn::make('name')->searchable()->sortable()->label(__('business.fields.name'))->toggleable(),
                TextColumn::make('unit.name')->searchable()->sortable()->label(__('business.fields.unit_id'))->toggleable(),
                TextColumn::make('base_unit_factor')->numeric(decimalPlaces: 6, locale: 'es')->sortable()->label(__('business.fields.base_unit_factor'))->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('available_for_purchase')->boolean()->label(__('business.fields.available_for_purchase'))->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('available_for_sale')->boolean()->label(__('business.fields.available_for_sale'))->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')->boolean()->label(__('business.fields.is_active'))->toggleable(),
                TextColumn::make('updated_at')->label(__('business.fields.updated_at'))->dateTime('d/m/Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])->filters([
                TernaryFilter::make('is_active')->label(__('business.fields.is_active')),
            ])->recordActions(ActionIconsHelper::configureActions([EditAction::make()]))->defaultSort('id', 'desc');
    }
}
