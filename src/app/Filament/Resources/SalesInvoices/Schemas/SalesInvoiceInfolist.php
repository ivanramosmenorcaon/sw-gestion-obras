<?php

namespace App\Filament\Resources\SalesInvoices\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SalesInvoiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('number')->label(__('billing.fields.number'))->placeholder(__('billing.options.draft')),
            TextEntry::make('status')->label(__('billing.fields.status'))->formatStateUsing(fn (string $state): string => __('billing.options.'.$state)),
            TextEntry::make('customer.legal_name')->state(fn (\App\Models\SalesInvoice $record): string => $record->recipient_snapshot['identity']['NombreRazon'] ?? $record->customer->legal_name)->label(__('billing.fields.customer_id')),
            TextEntry::make('issue_date')->label(__('billing.fields.issue_date'))->date('d/m/Y'),
            TextEntry::make('operation_date')->label(__('billing.fields.operation_date'))->date('d/m/Y'),
            TextEntry::make('description')->label(__('billing.fields.description')),
            Section::make(__('billing.summary'))->schema(array_map(fn (string $field): TextEntry => TextEntry::make($field)->state(fn (\App\Models\SalesInvoice $record): string => $record->status === 'draft' ? app(\App\Support\InvoiceCalculator::class)->calculate($record)['totals'][$field] : $record->$field)->label(__('billing.fields.'.$field))->money('EUR'),
                ['net_total', 'tax_total', 'surcharge_total', 'withholding_total', 'gross_total', 'payable_total']))->columns(3)->columnSpanFull(),
            Section::make(__('billing.registry'))->schema([
                TextEntry::make('billingRecord.hash')->label(__('billing.fields.hash'))->copyable(),
                TextEntry::make('billingRecord.generated_at')->label(__('billing.fields.generated_at')),
                TextEntry::make('billingRecord.submission.status')->label(__('billing.fields.status'))->formatStateUsing(fn (string $state): string => __('billing.options.'.$state)),
                TextEntry::make('billingRecord.submission.csv')->label(__('billing.fields.csv')),
                TextEntry::make('billingRecord.submission.error_description')->label(__('billing.fields.error_description')),
            ])->columnSpanFull(),
        ]);
    }
}
