<?php

namespace App\Filament\Resources\TaxProfiles\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TaxProfilesTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('code')->label(__('business.fields.code'))->searchable()->sortable(),
            TextColumn::make('name')->label(__('business.fields.name'))->searchable()->sortable(),
            TextColumn::make('tax_system')->label(__('fiscal.fields.tax_system'))->formatStateUsing(fn (string $state): string => __('fiscal.tax_systems.'.$state)),
            TextColumn::make('operation_class')->label(__('fiscal.fields.operation_class'))->formatStateUsing(fn (string $state): string => __('fiscal.operations.'.$state)),
            TextColumn::make('exemption_code')->label(__('fiscal.fields.exemption_code')),
            TextColumn::make('taxRate.name')->label(__('fiscal.fields.tax_rate_id')),
            TextColumn::make('surchargeRate.name')->label(__('fiscal.fields.surcharge_rate_id'))->toggleable(isToggledHiddenByDefault: true),
            IconColumn::make('requires_evidence')->label(__('fiscal.fields.requires_evidence'))->boolean(),
            IconColumn::make('is_active')->label(__('business.fields.is_active'))->boolean(),
        ])->filters([
            SelectFilter::make('tax_system')->label(__('fiscal.fields.tax_system'))->options(__('fiscal.tax_systems')),
            SelectFilter::make('operation_class')->label(__('fiscal.fields.operation_class'))->options(__('fiscal.operations')),
            TernaryFilter::make('is_active')->label(__('business.fields.is_active')),
        ])->recordActions([EditAction::make()])->defaultSort('name');
    }
}
