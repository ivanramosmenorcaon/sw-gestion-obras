<?php

namespace App\Filament\Resources\PartyAddresses\Schemas;

use App\Support\FiscalCatalog;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PartyAddressForm
{
    /** @param array<string, mixed> $context */
    public static function configure(Schema $schema, array $context = []): Schema
    {
        return $schema->columns(2)->components([
            (array_key_exists('party_id', $context) ? Hidden::make('party_id')->default($context['party_id']) : Select::make('party_id')->relationship('party', 'legal_name')->searchable()->preload())->label(__('business.fields.party_id'))->required(),
            TextInput::make('label')->label(__('business.fields.label'))->required()->maxLength(100),
            TextInput::make('address_line_1')->label(__('business.fields.address_line_1'))->required()->maxLength(255),
            TextInput::make('address_line_2')->label(__('business.fields.address_line_2'))->maxLength(255),
            TextInput::make('city')->label(__('business.fields.city'))->required()->maxLength(120),
            TextInput::make('province')->label(__('business.fields.province'))->maxLength(120),
            TextInput::make('postal_code')->label(__('business.fields.postal_code'))->maxLength(20),
            Select::make('country_code')->options(FiscalCatalog::countries())->searchable()->label(__('business.fields.country_code'))->required()->default('ES')->helperText(__('business.helpers.country')),
            Toggle::make('is_fiscal')->label(__('business.fields.is_fiscal'))->required()->default(false),
            Toggle::make('is_delivery')->label(__('business.fields.is_delivery'))->required()->default(false),
            Toggle::make('is_active')->label(__('business.fields.is_active'))->required()->default(true),
        ]);
    }
}
