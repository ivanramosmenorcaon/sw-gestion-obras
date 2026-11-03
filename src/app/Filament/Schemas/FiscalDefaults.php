<?php

namespace App\Filament\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Builder;

final class FiscalDefaults
{
    public static function make(bool $withIdentity = false): Section
    {
        $fields = [];
        if ($withIdentity) {
            $fields = [
                Select::make('taxpayer_capacity')->label(__('fiscal.fields.taxpayer_capacity'))->options(__('fiscal.capacities')),
                Select::make('tax_territory')->label(__('fiscal.fields.tax_territory'))->options(__('fiscal.territories')),
                TextInput::make('iae_code')->label(__('fiscal.fields.iae_code'))->maxLength(30),
                self::booleanSelect('withholding_obliged'),
                self::booleanSelect('equivalence_surcharge_applicable'),
            ];
        }
        foreach (['salesTaxProfile' => 'sales_tax_profile_id', 'purchaseTaxProfile' => 'purchase_tax_profile_id'] as $relationship => $field) {
            $fields[] = Select::make($field)->label(__('fiscal.fields.'.$field))->relationship($relationship, 'name')->searchable()->preload();
        }
        foreach (['salesWithholdingRate' => 'sales_withholding_rate_id', 'purchaseWithholdingRate' => 'purchase_withholding_rate_id'] as $relationship => $field) {
            $fields[] = Select::make($field)->label(__('fiscal.fields.'.$field))->relationship($relationship, 'name', modifyQueryUsing: fn (Builder $query): Builder => $query->where('kind', 'withholding'))->searchable()->preload();
        }
        $fields[] = Textarea::make('fiscal_notes')->label(__('fiscal.fields.fiscal_notes'))->columnSpanFull();

        return Section::make(__('fiscal.defaults'))->description(__('fiscal.help'))->schema($fields)->columns(2)->columnSpanFull();
    }

    private static function booleanSelect(string $field): Select
    {
        return Select::make($field)->label(__('fiscal.fields.'.$field))
            ->boolean(__('fiscal.yes'), __('fiscal.no'), __('fiscal.unknown'));
    }
}
