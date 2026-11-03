<?php

namespace App\Filament\Resources\SalesInvoices\Schemas;

use App\Models\SalesInvoice;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class SalesInvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('customer_id')->label(__('billing.fields.customer_id'))->relationship('customer', 'legal_name', fn (Builder $query) => $query->where('is_active', true)->whereHas('partyRoles', fn (Builder $roles) => $roles->where('role', 'customer')))->searchable()->preload()->required()->live()
                ->disabled(fn (?SalesInvoice $record): bool => (bool) $record?->lines()->exists())->dehydrated(),
            Select::make('invoice_series_id')->label(__('billing.fields.invoice_series_id'))->relationship('invoiceSeries', 'name', fn (Builder $query) => $query->where('is_active', true))->searchable()->preload()->required(),
            Select::make('work_order_id')->label(__('billing.fields.work_order_id'))->relationship('workOrder', 'reference', fn (Builder $query, Get $get) => $query->whereHas('project', fn (Builder $projects) => $projects->where('customer_id', $get('customer_id'))))->searchable()->preload(),
            DatePicker::make('issue_date')->label(__('billing.fields.issue_date'))->default(today('Europe/Madrid'))->required(),
            DatePicker::make('operation_date')->label(__('billing.fields.operation_date'))->default(today('Europe/Madrid'))->required(),
            DatePicker::make('due_date')->label(__('billing.fields.due_date'))->afterOrEqual('issue_date'),
            TextInput::make('description')->label(__('billing.fields.description'))->required()->maxLength(500)->columnSpanFull(),
            Textarea::make('fiscal_evidence')->label(__('billing.fields.fiscal_evidence'))->columnSpanFull(),
            Textarea::make('notes')->label(__('billing.fields.notes'))->columnSpanFull(),
        ]);
    }
}
