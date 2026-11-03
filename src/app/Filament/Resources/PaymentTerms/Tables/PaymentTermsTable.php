<?php

namespace App\Filament\Resources\PaymentTerms\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentTermsTable
{
    public static function configure(Table $table): Table
    {
        return $table->modifyQueryUsing(fn (Builder $query): Builder => $query->withExists(['paymentTermInstallments', 'customerSettings', 'supplierSettings']))->columns([
            TextColumn::make('code')->searchable()->sortable()->label(__('business.fields.code'))->toggleable(),
            TextColumn::make('name')->searchable()->sortable()->label(__('business.fields.name'))->toggleable(),
            IconColumn::make('is_active')->boolean()->label(__('business.fields.is_active'))->toggleable(),
            TextColumn::make('updated_at')->label(__('business.fields.updated_at'))->dateTime('d/m/Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            TernaryFilter::make('is_active')->label(__('business.fields.is_active')),
        ])->recordActions([EditAction::make()])->defaultSort('id', 'desc');
    }
}
