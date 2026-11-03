<?php

namespace App\Filament\Resources\ProductUnits\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ProductUnitForm
{
    /** @param array<string, mixed> $context */
    public static function configure(Schema $schema, array $context = []): Schema
    {
        return $schema->columns(2)->components([
            (array_key_exists('product_id', $context) ? Hidden::make('product_id')->default($context['product_id']) : Select::make('product_id')->relationship('product', 'name')->searchable()->preload()->live()->afterStateUpdated(function (Set $set): void {
                $set('product_unit_id', null);
            }))->label(__('business.fields.product_id'))->required(),
            TextInput::make('code')->label(__('business.fields.code'))->required()->maxLength(50),
            TextInput::make('name')->label(__('business.fields.name'))->required()->maxLength(100),
            (array_key_exists('unit_id', $context) ? Hidden::make('unit_id')->default($context['unit_id']) : Select::make('unit_id')->relationship('unit', 'name')->searchable()->preload())->label(__('business.fields.unit_id'))->required(),
            TextInput::make('base_unit_factor')->label(__('business.fields.base_unit_factor'))->required()->numeric()->minValue(0.000000000001)->helperText(__('business.helpers.factor')),
            Toggle::make('available_for_purchase')->label(__('business.fields.available_for_purchase'))->required()->default(true),
            Toggle::make('available_for_sale')->label(__('business.fields.available_for_sale'))->required()->default(true),
            Toggle::make('is_active')->label(__('business.fields.is_active'))->required()->default(true),
        ]);
    }
}
