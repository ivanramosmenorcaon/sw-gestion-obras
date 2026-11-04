<?php

namespace App\Filament\Resources\SalesInvoices\Schemas;

use App\Filament\Resources\Parties\PartyResource;
use App\Models\Party;
use App\Models\SalesInvoice;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class SalesInvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('customer_id')->label(__('billing.fields.customer_id'))->relationship('customer', 'legal_name', fn (Builder $query) => $query->where('is_active', true)->whereHas('partyRoles', fn (Builder $roles) => $roles->where('role', 'customer')))->searchable()->preload()->required()->live()
                ->disabled(fn (?SalesInvoice $record): bool => (bool) $record?->lines()->exists())->dehydrated()
                ->helperText(__('billing.customer_address_help')),
            TextEntry::make('customer_fiscal_address')->label(__('billing.customer_fiscal_address'))
                ->visible(fn (Get $get): bool => filled($get('customer_id')))
                ->state(function (Get $get): string {
                    $customer = Party::find($get('customer_id'));
                    if (! $customer || ! PartyResource::canView($customer)) {
                        return '—';
                    }
                    $addresses = $customer->partyAddresses()->where('is_active', true)->where('is_fiscal', true)->get();
                    if ($addresses->count() !== 1) {
                        return __('billing.customer_address_count', ['count' => $addresses->count()]);
                    }
                    $address = $addresses->first();
                    $missing = collect(['address_line_1', 'city', 'postal_code', 'country_code'])->filter(fn (string $field): bool => blank($address->$field));
                    if ($missing->isNotEmpty()) {
                        return __('billing.customer_address_incomplete', ['fields' => $missing->map(fn (string $field): string => __('business.fields.'.$field))->implode(', ')]);
                    }

                    return collect([$address->address_line_1, $address->address_line_2, $address->postal_code, $address->city, $address->province, $address->country_code])->filter()->implode(', ');
                }),
            Actions::make([
                Action::make('manageCustomerAddress')->label(__('billing.manage_customer_address'))
                    ->visible(function (Get $get): bool {
                        $customer = Party::find($get('customer_id'));

                        return $customer && PartyResource::canView($customer) && PartyResource::canEdit($customer);
                    })
                    ->url(fn (Get $get): string => PartyResource::getUrl('edit', ['record' => $get('customer_id')]))->openUrlInNewTab(),
            ]),
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
