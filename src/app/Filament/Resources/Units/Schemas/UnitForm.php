<?php

namespace App\Filament\Resources\Units\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UnitForm
{
    /** @param array<string, mixed> $context */
    public static function configure(Schema $schema, array $context = []): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('code')->label(__('business.fields.code'))->required()->maxLength(20)->unique(ignoreRecord: true),
            TextInput::make('name')->label(__('business.fields.name'))->required()->maxLength(100),
            TextInput::make('symbol')->label(__('business.fields.symbol'))->required()->maxLength(20),
            Select::make('magnitude')->options(['count' => __('business.options.count'), 'time' => __('business.options.time'), 'mass' => __('business.options.mass'), 'length' => __('business.options.length'), 'area' => __('business.options.area'), 'volume' => __('business.options.volume')])->label(__('business.fields.magnitude'))->required(),
            TextInput::make('display_precision')->label(__('business.fields.display_precision'))->required()->default('3')->numeric()->minValue(0)->integer()->maxValue(12),
            Toggle::make('is_active')->label(__('business.fields.is_active'))->required()->default(true),
        ]);
    }
}
