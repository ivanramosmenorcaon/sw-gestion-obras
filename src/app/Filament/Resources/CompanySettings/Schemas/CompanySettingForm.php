<?php

namespace App\Filament\Resources\CompanySettings\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CompanySettingForm
{
    /** @param array<string, mixed> $context */
    public static function configure(Schema $schema, array $context = []): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('legal_name')->label(__('business.fields.legal_name'))->required()->maxLength(255),
            TextInput::make('trade_name')->label(__('business.fields.trade_name'))->maxLength(255),
            TextInput::make('tax_identification_type')->label(__('business.fields.tax_identification_type'))->required()->default('nif')->maxLength(30),
            TextInput::make('tax_identification_number')->label(__('business.fields.tax_identification_number'))->required()->maxLength(40),
            TextInput::make('address_line_1')->label(__('business.fields.address_line_1'))->required()->maxLength(255),
            TextInput::make('address_line_2')->label(__('business.fields.address_line_2'))->maxLength(255),
            TextInput::make('city')->label(__('business.fields.city'))->required()->maxLength(120),
            TextInput::make('province')->label(__('business.fields.province'))->maxLength(120),
            TextInput::make('postal_code')->label(__('business.fields.postal_code'))->maxLength(20),
            TextInput::make('country_code')->label(__('business.fields.country_code'))->required()->default('ES')->maxLength(2)->helperText(__('business.helpers.country')),
            TextInput::make('currency_code')->label(__('business.fields.currency_code'))->required()->default('EUR')->maxLength(3)->helperText(__('business.helpers.currency')),
            TextInput::make('timezone')->label(__('business.fields.timezone'))->required()->default('Europe/Madrid')->maxLength(64),
            TextInput::make('email')->label(__('business.fields.email'))->maxLength(255)->email(),
            TextInput::make('phone')->label(__('business.fields.phone'))->maxLength(40)->tel(),
        ]);
    }
}
