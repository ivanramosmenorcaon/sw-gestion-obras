<?php

namespace App\Filament\Resources\PaymentTermInstallments\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PaymentTermInstallmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('paymentTerm.name')->searchable()->sortable()->label(__('business.fields.payment_term_id'))->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('sequence')->searchable()->sortable()->label(__('business.fields.sequence'))->toggleable(),
            TextColumn::make('percentage')->numeric(decimalPlaces: 4, locale: 'es')->sortable()->label(__('business.fields.percentage'))->toggleable(),
            TextColumn::make('days_after_reference')->searchable()->sortable()->label(__('business.fields.days_after_reference'))->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('reference_event')->badge()->formatStateUsing(fn (?string $state): string => $state === null ? '' : __('business.options.'.$state))->label(__('business.fields.reference_event'))->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')->label(__('business.fields.updated_at'))->dateTime('d/m/Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            SelectFilter::make('reference_event')->label(__('business.fields.reference_event'))->options(['invoice_date' => __('business.options.invoice_date'), 'work_start' => __('business.options.work_start'), 'work_completion' => __('business.options.work_completion')]),
        ])->recordActions([EditAction::make()])->defaultSort('id', 'desc');
    }
}
