<?php

namespace App\Filament\Resources\PriceLists\Tables;

use App\Filament\Support\ActionIconsHelper;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PriceListsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->dragReorderableColumns()
            ->stickableColumns()
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withExists(['priceListItems', 'customerSettings']))->columns([
                TextColumn::make('code')->searchable()->sortable()->label(__('business.fields.code'))->toggleable(),
                TextColumn::make('name')->searchable()->sortable()->label(__('business.fields.name'))->toggleable(),
                TextColumn::make('currency_code')->searchable()->sortable()->label(__('business.fields.currency_code'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('valid_from')->date('d/m/Y')->sortable()->label(__('business.fields.valid_from'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('valid_until')->date('d/m/Y')->sortable()->label(__('business.fields.valid_until'))->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')->boolean()->label(__('business.fields.is_active'))->toggleable(),
                TextColumn::make('updated_at')->label(__('business.fields.updated_at'))->dateTime('d/m/Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])->filters([
                TernaryFilter::make('is_active')->label(__('business.fields.is_active')),
            ])->recordActions(ActionIconsHelper::configureActions([EditAction::make()]))->defaultSort('id', 'desc');
    }
}
