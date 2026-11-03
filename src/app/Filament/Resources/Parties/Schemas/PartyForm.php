<?php

namespace App\Filament\Resources\Parties\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PartyForm
{
    /** @param array<string, mixed> $context */
    public static function configure(Schema $schema, array $context = []): Schema
    {
        return $schema->columns(2)->components([
            Select::make('type')->options(['individual' => __('business.options.individual'), 'organization' => __('business.options.organization')])->label(__('business.fields.type'))->required(),
            TextInput::make('legal_name')->label(__('business.fields.legal_name'))->required()->maxLength(255)->helperText(__('business.helpers.role')),
            TextInput::make('trade_name')->label(__('business.fields.trade_name'))->maxLength(255),
            TextInput::make('tax_country_code')->label(__('business.fields.tax_country_code'))->required()->default('ES')->maxLength(2)->helperText(__('business.helpers.country')),
            TextInput::make('tax_identification_type')->label(__('business.fields.tax_identification_type'))->required()->default('nif')->maxLength(30),
            TextInput::make('tax_identification_number')->label(__('business.fields.tax_identification_number'))->maxLength(40),
            TextInput::make('locale')->label(__('business.fields.locale'))->required()->default('es')->maxLength(10),
            Textarea::make('notes')->rows(3)->columnSpanFull()->label(__('business.fields.notes')),
            Toggle::make('is_active')->label(__('business.fields.is_active'))->required()->default(true),
            Section::make(__('business.sections.customer_settings'))->relationship('customerSettings')->schema([Select::make('payment_term_id')->relationship('paymentTerm', 'name')->searchable()->preload()->label(__('business.fields.payment_term_id')), Select::make('price_list_id')->relationship('priceList', 'name')->searchable()->preload()->label(__('business.fields.price_list_id')), TextInput::make('customer_reference')->maxLength(100)->label(__('business.fields.customer_reference'))])->columns(2)->columnSpanFull(),
            Section::make(__('business.sections.supplier_settings'))->relationship('supplierSettings')->schema([Select::make('payment_term_id')->relationship('paymentTerm', 'name')->searchable()->preload()->label(__('business.fields.payment_term_id')), TextInput::make('account_reference')->maxLength(100)->label(__('business.fields.account_reference'))])->columns(2)->columnSpanFull(),
        ]);
    }
}
