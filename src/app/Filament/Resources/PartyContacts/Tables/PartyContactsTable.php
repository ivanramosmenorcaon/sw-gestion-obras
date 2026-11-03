<?php

namespace App\Filament\Resources\PartyContacts\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PartyContactsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->dragReorderableColumns()
            ->stickableColumns()
            ->columns([
                TextColumn::make('party.legal_name')->searchable()->sortable()->label(__('business.fields.party_id'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('name')->searchable()->sortable()->label(__('business.fields.name'))->toggleable(),
                TextColumn::make('position')->searchable()->sortable()->label(__('business.fields.job_title'))->toggleable(),
                TextColumn::make('email')->searchable()->sortable()->label(__('business.fields.email'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phone')->searchable()->sortable()->label(__('business.fields.phone'))->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_primary')->boolean()->label(__('business.fields.is_primary'))->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')->boolean()->label(__('business.fields.is_active'))->toggleable(),
                TextColumn::make('updated_at')->label(__('business.fields.updated_at'))->dateTime('d/m/Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])->filters([
                TernaryFilter::make('is_active')->label(__('business.fields.is_active')),
            ])->recordActions([EditAction::make()])->defaultSort('id', 'desc');
    }
}
