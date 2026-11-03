<?php

namespace App\Filament\Resources\TaxProfiles\Schemas;

use App\Support\FiscalCatalog;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class TaxProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('code')->label(__('business.fields.code'))->required()->maxLength(60)->unique(ignoreRecord: true),
            TextInput::make('name')->label(__('business.fields.name'))->required()->maxLength(255),
            Select::make('tax_system')->label(__('fiscal.fields.tax_system'))->options(__('fiscal.tax_systems'))->default('01')->required()->live()
                ->afterStateUpdated(function (Set $set, ?string $state): void {
                    $set('regime_code', $state === '05' ? null : '01');
                    $set('tax_rate_id', null);
                    $set('surcharge_rate_id', null);
                    $set('exemption_code', null);
                }),
            Select::make('regime_code')->label(__('fiscal.fields.regime_code'))->options(fn (Get $get): array => FiscalCatalog::regimes($get('tax_system')))->searchable()->required(fn (Get $get): bool => $get('tax_system') !== '05')->default('01'),
            Select::make('operation_class')->label(__('fiscal.fields.operation_class'))->options(__('fiscal.operations'))->required()->live()
                ->afterStateUpdated(function (Set $set): void {
                    $set('exemption_code', null);
                    $set('tax_rate_id', null);
                    $set('surcharge_rate_id', null);
                }),
            Select::make('exemption_code')->label(__('fiscal.fields.exemption_code'))->options(fn (Get $get): array => FiscalCatalog::exemptions($get('tax_system')))
                ->visible(fn (Get $get): bool => $get('operation_class') === 'exempt')->required(fn (Get $get): bool => $get('operation_class') === 'exempt')->dehydratedWhenHidden(),
            Select::make('tax_rate_id')->label(__('fiscal.fields.tax_rate_id'))->relationship('taxRate', 'name', modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query->where('kind', ['01' => 'vat', '02' => 'ipsi', '03' => 'igic', '05' => 'other'][$get('tax_system') ?? '01']))
                ->searchable()->preload()->visible(fn (Get $get): bool => in_array($get('operation_class'), ['S1', 'S2'], true))->required(fn (Get $get): bool => in_array($get('operation_class'), ['S1', 'S2'], true))->dehydratedWhenHidden(),
            Select::make('surcharge_rate_id')->label(__('fiscal.fields.surcharge_rate_id'))->relationship('surchargeRate', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query->where('kind', 'surcharge'))->searchable()->preload()
                ->visible(fn (Get $get): bool => $get('tax_system') === '01' && $get('operation_class') === 'S1')->dehydratedWhenHidden(),
            Textarea::make('legal_basis')->label(__('fiscal.fields.legal_basis'))->required(fn (Get $get): bool => in_array($get('operation_class'), ['exempt', 'S2', 'N1', 'N2'], true))->columnSpanFull(),
            Toggle::make('requires_evidence')->label(__('fiscal.fields.requires_evidence'))->default(false),
            Toggle::make('is_active')->label(__('business.fields.is_active'))->default(true),
        ]);
    }
}
