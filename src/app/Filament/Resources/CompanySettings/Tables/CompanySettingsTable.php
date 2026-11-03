<?php

namespace App\Filament\Resources\CompanySettings\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CompanySettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->dragReorderableColumns()
            ->stickableColumns()
            ->columns([
                TextColumn::make('legal_name')->searchable()->sortable()->label(__('business.fields.legal_name'))->toggleable(),
                TextColumn::make('trade_name')->searchable()->sortable()->label(__('business.fields.trade_name'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tax_identification_type')->searchable()->sortable()->label(__('business.fields.tax_identification_type'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tax_identification_number')->searchable()->sortable()->label(__('business.fields.tax_identification_number'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('address_line_1')->searchable()->sortable()->label(__('business.fields.address_line_1'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('address_line_2')->searchable()->sortable()->label(__('business.fields.address_line_2'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('city')->searchable()->sortable()->label(__('business.fields.city'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('province')->searchable()->sortable()->label(__('business.fields.province'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('postal_code')->searchable()->sortable()->label(__('business.fields.postal_code'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('country_code')->searchable()->sortable()->label(__('business.fields.country_code'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('currency_code')->searchable()->sortable()->label(__('business.fields.currency_code'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('timezone')->searchable()->sortable()->label(__('business.fields.timezone'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('email')->searchable()->sortable()->label(__('business.fields.email'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phone')->searchable()->sortable()->label(__('business.fields.phone'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->label(__('business.fields.updated_at'))->dateTime('d/m/Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])->filters([
            ])->recordActions([EditAction::make()])->defaultSort('id', 'desc');
    }
}
