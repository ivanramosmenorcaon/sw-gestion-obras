<?php

namespace App\Filament\Resources\InvoiceSeries\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InvoiceSeriesTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('code')->label(__('billing.fields.code'))->searchable(),
            TextColumn::make('name')->label(__('billing.fields.name'))->searchable(),
            TextColumn::make('fiscal_year')->label(__('billing.fields.fiscal_year'))->sortable(),
            TextColumn::make('kind')->label(__('billing.fields.kind'))->formatStateUsing(fn (string $state): string => __('billing.options.'.$state)),
            TextColumn::make('environment')->label(__('billing.fields.environment'))->formatStateUsing(fn (string $state): string => __('billing.options.'.$state)),
            TextColumn::make('next_number')->label(__('billing.fields.next_number')),
        ])->recordActions([EditAction::make()])->dragReorderableColumns()->stickableColumns();
    }
}
