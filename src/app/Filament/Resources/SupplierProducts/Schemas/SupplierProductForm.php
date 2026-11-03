<?php

namespace App\Filament\Resources\SupplierProducts\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SupplierProductForm
{
    /** @param array<string, mixed> $context */
    public static function configure(Schema $schema, array $context = []): Schema
    {
        return $schema->columns(2)->components([
            (array_key_exists('supplier_id', $context) ? Hidden::make('supplier_id')->default($context['supplier_id']) : Select::make('supplier_id')->relationship('supplier', 'legal_name', modifyQueryUsing: function (Builder $query, Get $get, ?Model $record): Builder {
                $query->whereHas('partyRoles', fn (Builder $roles): Builder => $roles->where('role', 'supplier'));

                return $query;
            })->searchable()->preload())->label(__('business.fields.supplier_id'))->required(),
            (array_key_exists('product_id', $context) ? Hidden::make('product_id')->default($context['product_id']) : Select::make('product_id')->relationship('product', 'name')->searchable()->preload()->live()->afterStateUpdated(function (Set $set): void {
                $set('product_unit_id', null);
            }))->label(__('business.fields.product_id'))->required(),
            (array_key_exists('product_unit_id', $context) ? Hidden::make('product_unit_id')->default($context['product_unit_id']) : Select::make('product_unit_id')->relationship('productUnit', 'name', modifyQueryUsing: function (Builder $query, Get $get, ?Model $record): Builder {
                $query->where('product_id', $get('product_id'));

                return $query;
            })->searchable()->preload())->label(__('business.fields.product_unit_id'))->required(),
            TextInput::make('supplier_reference')->label(__('business.fields.supplier_reference'))->required()->maxLength(100),
            TextInput::make('minimum_order_quantity')->label(__('business.fields.minimum_order_quantity'))->required()->default('1')->numeric()->minValue(0),
            Toggle::make('is_active')->label(__('business.fields.is_active'))->required()->default(true),
        ]);
    }
}
