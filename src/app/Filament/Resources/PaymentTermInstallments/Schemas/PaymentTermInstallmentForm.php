<?php

namespace App\Filament\Resources\PaymentTermInstallments\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PaymentTermInstallmentForm
{
    /** @param array<string, mixed> $context */
    public static function configure(Schema $schema, array $context = []): Schema
    {
        return $schema->columns(2)->components([
            (array_key_exists('payment_term_id', $context) ? Hidden::make('payment_term_id')->default($context['payment_term_id']) : Select::make('payment_term_id')->relationship('paymentTerm', 'name')->searchable()->preload())->label(__('business.fields.payment_term_id'))->required(),
            TextInput::make('sequence')->label(__('business.fields.sequence'))->required()->numeric()->minValue(1)->integer(),
            TextInput::make('percentage')->label(__('business.fields.percentage'))->required()->numeric()->minValue(0.0001)->maxValue(100),
            TextInput::make('days_after_reference')->label(__('business.fields.days_after_reference'))->required()->default('0')->numeric()->minValue(0)->integer(),
            Select::make('reference_event')->options(['invoice_date' => __('business.options.invoice_date'), 'work_start' => __('business.options.work_start'), 'work_completion' => __('business.options.work_completion')])->label(__('business.fields.reference_event'))->required()->default('invoice_date'),
        ]);
    }
}
