<?php

namespace App\Filament\Resources\UnitConversions\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UnitConversionsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('fromUnit.name')->searchable()->sortable()->label(__('business.fields.from_unit_id'))->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('toUnit.name')->searchable()->sortable()->label(__('business.fields.to_unit_id'))->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('factor')->numeric(decimalPlaces: 6, locale: 'es')->sortable()->label(__('business.fields.factor'))->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')->label(__('business.fields.updated_at'))->dateTime('d/m/Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
        ])->recordActions([EditAction::make()])->defaultSort('id', 'desc');
    }
}
