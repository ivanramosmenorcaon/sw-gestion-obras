<?php

namespace App\Filament\Resources\LaborCostRates\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LaborCostRatesTable
{
    public static function configure(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->columns([TextColumn::make('employee.name')->label(__('costing.fields.employee_id')), TextColumn::make('contractor.legal_name')->label(__('costing.fields.contractor_id')), TextColumn::make('hourly_cost')->label(__('costing.fields.hourly_cost'))->money('EUR'), TextColumn::make('valid_from')->label(__('costing.fields.valid_from')), TextColumn::make('valid_until')->label(__('costing.fields.valid_until'))])->recordActions([ViewAction::make(), EditAction::make()])->dragReorderableColumns()->stickableColumns();
    }
}
