<?php

namespace App\Filament\Resources\TaxRates\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class TaxRateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('code')->label(__('business.fields.code'))->required()->maxLength(60)->unique(ignoreRecord: true),
            TextInput::make('name')->label(__('business.fields.name'))->required()->maxLength(255),
            Select::make('kind')->label(__('fiscal.fields.kind'))->options(__('fiscal.rate_kinds'))->required(),
            TextInput::make('percentage')->label(__('fiscal.fields.percentage'))->numeric()->minValue(0)->maxValue(100)->required(),
            DatePicker::make('valid_from')->label(__('fiscal.fields.valid_from'))->native(false)->displayFormat('d/m/Y')->required(),
            DatePicker::make('valid_until')->label(__('fiscal.fields.valid_until'))->native(false)->displayFormat('d/m/Y')->afterOrEqual(fn (Get $get): ?string => $get('valid_from')),
            Textarea::make('legal_basis')->label(__('fiscal.fields.legal_basis'))->columnSpanFull(),
            Toggle::make('requires_evidence')->label(__('fiscal.fields.requires_evidence'))->default(false),
            Toggle::make('is_active')->label(__('business.fields.is_active'))->default(true),
        ]);
    }
}
