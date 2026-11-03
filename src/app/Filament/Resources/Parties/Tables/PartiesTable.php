<?php

namespace App\Filament\Resources\Parties\Tables;

use App\Support\FiscalCatalog;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PartiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->dragReorderableColumns()
            ->stickableColumns()
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withExists(['projects', 'workOrderContractorAssignments', 'partyRoles', 'partyAddresses', 'partyContacts', 'supplierProducts', 'customerSettings', 'supplierSettings']))->columns([
                TextColumn::make('type')->badge()->formatStateUsing(fn (?string $state): string => $state === null ? '' : __('business.options.'.$state))->label(__('business.fields.type'))->toggleable(),
                TextColumn::make('legal_name')->searchable()->sortable()->label(__('business.fields.legal_name'))->toggleable(),
                TextColumn::make('trade_name')->searchable()->sortable()->label(__('business.fields.trade_name'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tax_country_code')->searchable()->sortable()->label(__('business.fields.tax_country_code'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tax_identification_type')->searchable()->sortable()->formatStateUsing(fn (string $state): string => FiscalCatalog::identityTypes()[$state] ?? $state)->label(__('business.fields.tax_identification_type'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('salesTaxProfile.name')->label(__('fiscal.fields.sales_tax_profile_id'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tax_territory')->label(__('fiscal.fields.tax_territory'))->formatStateUsing(fn (?string $state): string => $state ? __('fiscal.territories.'.$state) : __('fiscal.unknown'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tax_identification_number')->searchable()->sortable()->label(__('business.fields.tax_identification_number'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('locale')->searchable()->sortable()->label(__('business.fields.locale'))->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')->boolean()->label(__('business.fields.is_active'))->toggleable(),
                TextColumn::make('updated_at')->label(__('business.fields.updated_at'))->dateTime('d/m/Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])->filters([
                SelectFilter::make('tax_identification_type')->label(__('business.fields.tax_identification_type'))->options(FiscalCatalog::identityTypes()),
                SelectFilter::make('tax_territory')->label(__('fiscal.fields.tax_territory'))->options(__('fiscal.territories')),
                SelectFilter::make('type')->label(__('business.fields.type'))->options(['individual' => __('business.options.individual'), 'organization' => __('business.options.organization')]),
                TernaryFilter::make('is_active')->label(__('business.fields.is_active')),
                SelectFilter::make('role')->label(__('business.models.party_roles.singular'))->options(['customer' => __('business.options.customer'), 'supplier' => __('business.options.supplier'), 'subcontractor' => __('business.options.subcontractor')])->query(fn (Builder $query, array $data): Builder => $query->when($data['value'] ?? null, fn (Builder $q, string $role): Builder => $q->whereHas('partyRoles', fn (Builder $roles): Builder => $roles->where('role', $role)))),
            ])->recordActions([EditAction::make()])->defaultSort('id', 'desc');
    }
}
