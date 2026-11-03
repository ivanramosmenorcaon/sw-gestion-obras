<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table->modifyQueryUsing(fn (Builder $query): Builder => $query->withExists(['workOrderLines', 'productUnits', 'supplierProducts', 'priceListItems']))->columns([
            TextColumn::make('reference')->searchable()->sortable()->label(__('business.fields.reference'))->toggleable(),
            TextColumn::make('name')->searchable()->sortable()->label(__('business.fields.name'))->toggleable(),
            TextColumn::make('productCategory.name')->searchable()->sortable()->label(__('business.fields.product_category_id'))->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('type')->badge()->formatStateUsing(fn (?string $state): string => $state === null ? '' : __('business.options.'.$state))->label(__('business.fields.type'))->toggleable(),
            TextColumn::make('baseUnit.name')->searchable()->sortable()->label(__('business.fields.base_unit_id'))->toggleable(isToggledHiddenByDefault: true),
            IconColumn::make('tracks_stock')->boolean()->label(__('business.fields.tracks_stock'))->toggleable(isToggledHiddenByDefault: true),
            IconColumn::make('is_active')->boolean()->label(__('business.fields.is_active'))->toggleable(),
            TextColumn::make('updated_at')->label(__('business.fields.updated_at'))->dateTime('d/m/Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            SelectFilter::make('type')->label(__('business.fields.type'))->options(['material' => __('business.options.material'), 'service' => __('business.options.service'), 'labor' => __('business.options.labor'), 'rental' => __('business.options.rental')]),
            TernaryFilter::make('is_active')->label(__('business.fields.is_active')),
        ])->recordActions([EditAction::make()])->defaultSort('id', 'desc');
    }
}
