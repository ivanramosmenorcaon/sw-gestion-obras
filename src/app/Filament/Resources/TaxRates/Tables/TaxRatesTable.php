<?php

namespace App\Filament\Resources\TaxRates\Tables;

use App\Filament\Support\ActionIconsHelper;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TaxRatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->dragReorderableColumns()
            ->stickableColumns()
            ->columns([
                TextColumn::make('code')->label(__('business.fields.code'))->searchable()->sortable(),
                TextColumn::make('name')->label(__('business.fields.name'))->searchable()->sortable(),
                TextColumn::make('kind')->label(__('fiscal.fields.kind'))->formatStateUsing(fn (string $state): string => __('fiscal.rate_kinds.'.$state)),
                TextColumn::make('percentage')->label(__('fiscal.fields.percentage'))->numeric(decimalPlaces: 4, locale: 'es'),
                TextColumn::make('valid_from')->label(__('fiscal.fields.valid_from'))->date('d/m/Y'),
                TextColumn::make('valid_until')->label(__('fiscal.fields.valid_until'))->date('d/m/Y'),
                IconColumn::make('requires_evidence')->label(__('fiscal.fields.requires_evidence'))->boolean(),
                IconColumn::make('is_active')->label(__('business.fields.is_active'))->boolean(),
            ])->filters([
                SelectFilter::make('kind')->label(__('fiscal.fields.kind'))->options(__('fiscal.rate_kinds')),
                TernaryFilter::make('is_active')->label(__('business.fields.is_active')),
            ])->recordActions(ActionIconsHelper::configureActions([EditAction::make()]))->defaultSort('name');
    }
}
