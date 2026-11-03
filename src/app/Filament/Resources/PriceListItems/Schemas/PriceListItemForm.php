<?php

namespace App\Filament\Resources\PriceListItems\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PriceListItemForm
{
    /** @param array<string, mixed> $context */
    public static function configure(Schema $schema, array $context = []): Schema
    {
        return $schema->columns(2)->components([
            (array_key_exists('price_list_id', $context) ? Hidden::make('price_list_id')->default($context['price_list_id']) : Select::make('price_list_id')->relationship('priceList', 'name')->searchable()->preload())->label(__('business.fields.price_list_id'))->required(),
            (array_key_exists('product_id', $context) ? Hidden::make('product_id')->default($context['product_id']) : Select::make('product_id')->relationship('product', 'name')->searchable()->preload()->live()->afterStateUpdated(function (Set $set): void {
                $set('product_unit_id', null);
            }))->label(__('business.fields.product_id'))->required(),
            (array_key_exists('product_unit_id', $context) ? Hidden::make('product_unit_id')->default($context['product_unit_id']) : Select::make('product_unit_id')->relationship('productUnit', 'name', modifyQueryUsing: function (Builder $query, Get $get, ?Model $record): Builder {
                $query->where('product_id', $get('product_id'));

                return $query;
            })->searchable()->preload())->label(__('business.fields.product_unit_id'))->required(),
            TextInput::make('minimum_quantity')->label(__('business.fields.minimum_quantity'))->required()->default('1')->numeric()->minValue(0),
            TextInput::make('unit_price')->label(__('business.fields.unit_price'))->required()->numeric()->minValue(0),
            DatePicker::make('valid_from')->native(false)->displayFormat('d/m/Y')->label(__('business.fields.valid_from'))->required(),
            DatePicker::make('valid_until')->native(false)->displayFormat('d/m/Y')->label(__('business.fields.valid_until'))->afterOrEqual(fn (Get $get): ?string => $get('valid_from')),
        ]);
    }
}
