<?php

namespace App\Filament\Resources\PartyRoles\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PartyRolesTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('party.legal_name')->searchable()->sortable()->label(__('business.fields.party_id'))->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('role')->badge()->formatStateUsing(fn (?string $state): string => $state === null ? '' : __('business.options.'.$state))->label(__('business.fields.role'))->toggleable(),
            TextColumn::make('updated_at')->label(__('business.fields.updated_at'))->dateTime('d/m/Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            SelectFilter::make('role')->label(__('business.fields.role'))->options(['customer' => __('business.options.customer'), 'supplier' => __('business.options.supplier'), 'subcontractor' => __('business.options.subcontractor')]),
        ])->recordActions([EditAction::make()])->defaultSort('id', 'desc');
    }
}
