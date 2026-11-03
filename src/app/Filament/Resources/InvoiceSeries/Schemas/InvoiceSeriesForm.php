<?php

namespace App\Filament\Resources\InvoiceSeries\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class InvoiceSeriesForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label(__('billing.fields.code'))->required()->maxLength(15)->regex('/^[A-Z0-9-]+$/'),
            TextInput::make('name')->label(__('billing.fields.name'))->required()->maxLength(100),
            TextInput::make('fiscal_year')->label(__('billing.fields.fiscal_year'))->integer()->required()->minValue(2026)->maxValue(2100)->default(today('Europe/Madrid')->year),
            Select::make('kind')->label(__('billing.fields.kind'))->options(collect(['standard', 'rectifying'])->mapWithKeys(fn (string $key): array => [$key => __('billing.options.'.$key)]))->default('standard')->required(),
            Select::make('environment')->label(__('billing.fields.environment'))->options(['test' => __('billing.options.test')])->default('test')->required(),
            Toggle::make('is_active')->label(__('billing.fields.is_active'))->default(true),
        ]);
    }
}
