<?php

namespace App\Filament\Resources\PriceLists\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PriceListForm
{
    /** @param array<string, mixed> $context */
    public static function configure(Schema $schema, array $context = []): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('code')->label(__('business.fields.code'))->required()->maxLength(50)->unique(ignoreRecord: true),
            TextInput::make('name')->label(__('business.fields.name'))->required()->maxLength(255),
            TextInput::make('currency_code')->label(__('business.fields.currency_code'))->required()->default('EUR')->maxLength(3)->helperText(__('business.helpers.currency')),
            DatePicker::make('valid_from')->native(false)->displayFormat('d/m/Y')->label(__('business.fields.valid_from')),
            DatePicker::make('valid_until')->native(false)->displayFormat('d/m/Y')->label(__('business.fields.valid_until'))->afterOrEqual(fn (Get $get): ?string => $get('valid_from')),
            Toggle::make('is_active')->label(__('business.fields.is_active'))->required()->default(true),
        ]);
    }
}
