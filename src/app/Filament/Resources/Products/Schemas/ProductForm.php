<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Filament\Schemas\FiscalDefaults;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductForm
{
    /** @param array<string, mixed> $context */
    public static function configure(Schema $schema, array $context = []): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('sales_markup_percentage')->label(__('costing.fields.sales_markup_percentage'))->numeric()->minValue(0)->maxValue(10000)->step('0.0001')->helperText(__('costing.product_markup_help')),
            FiscalDefaults::make(false),
            TextInput::make('reference')->label(__('business.fields.reference'))->required()->maxLength(80)->unique(ignoreRecord: true),
            TextInput::make('name')->label(__('business.fields.name'))->required()->maxLength(255),
            Textarea::make('description')->rows(3)->columnSpanFull()->label(__('business.fields.description')),
            (array_key_exists('product_category_id', $context) ? Hidden::make('product_category_id')->default($context['product_category_id']) : Select::make('product_category_id')->relationship('productCategory', 'name')->searchable()->preload())->label(__('business.fields.product_category_id')),
            Select::make('type')->options(['material' => __('business.options.material'), 'service' => __('business.options.service'), 'labor' => __('business.options.labor'), 'rental' => __('business.options.rental')])->label(__('business.fields.type'))->required(),
            (array_key_exists('base_unit_id', $context) ? Hidden::make('base_unit_id')->default($context['base_unit_id']) : Select::make('base_unit_id')->relationship('baseUnit', 'name')->searchable()->preload())->label(__('business.fields.base_unit_id'))->required(),
            Toggle::make('tracks_stock')->label(__('business.fields.tracks_stock'))->required()->default(false),
            Toggle::make('is_active')->label(__('business.fields.is_active'))->required()->default(true),
        ]);
    }
}
