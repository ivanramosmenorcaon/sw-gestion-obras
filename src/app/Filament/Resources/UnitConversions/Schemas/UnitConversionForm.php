<?php

namespace App\Filament\Resources\UnitConversions\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UnitConversionForm
{
    /** @param array<string, mixed> $context */
    public static function configure(Schema $schema, array $context = []): Schema
    {
        return $schema->columns(2)->components([
            (array_key_exists('from_unit_id', $context) ? Hidden::make('from_unit_id')->default($context['from_unit_id']) : Select::make('from_unit_id')->relationship('fromUnit', 'name')->searchable()->preload())->label(__('business.fields.from_unit_id'))->required(),
            (array_key_exists('to_unit_id', $context) ? Hidden::make('to_unit_id')->default($context['to_unit_id']) : Select::make('to_unit_id')->relationship('toUnit', 'name')->searchable()->preload())->label(__('business.fields.to_unit_id'))->required(),
            TextInput::make('factor')->label(__('business.fields.factor'))->required()->numeric()->minValue(0.000000000001),
        ]);
    }
}
