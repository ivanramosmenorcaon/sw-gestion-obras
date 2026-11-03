<?php

namespace App\Filament\Resources\PartyAddresses\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PartyAddressesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->dragReorderableColumns()
            ->stickableColumns()
            ->columns([
                TextColumn::make('party.legal_name')->searchable()->sortable()->label(__('business.fields.party_id'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('label')->searchable()->sortable()->label(__('business.fields.label'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('address_line_1')->searchable()->sortable()->label(__('business.fields.address_line_1'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('address_line_2')->searchable()->sortable()->label(__('business.fields.address_line_2'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('city')->searchable()->sortable()->label(__('business.fields.city'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('province')->searchable()->sortable()->label(__('business.fields.province'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('postal_code')->searchable()->sortable()->label(__('business.fields.postal_code'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('country_code')->searchable()->sortable()->label(__('business.fields.country_code'))->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_fiscal')->boolean()->label(__('business.fields.is_fiscal'))->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_delivery')->boolean()->label(__('business.fields.is_delivery'))->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')->boolean()->label(__('business.fields.is_active'))->toggleable(),
                TextColumn::make('updated_at')->label(__('business.fields.updated_at'))->dateTime('d/m/Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])->filters([
                TernaryFilter::make('is_active')->label(__('business.fields.is_active')),
            ])->recordActions([EditAction::make()])->defaultSort('id', 'desc');
    }
}
