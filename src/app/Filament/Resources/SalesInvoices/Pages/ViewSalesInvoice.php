<?php

namespace App\Filament\Resources\SalesInvoices\Pages;

use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use App\Jobs\SubmitBillingRecord;
use App\Support\SalesBilling;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Builder;

class ViewSalesInvoice extends ViewRecord
{
    protected static string $resource = SalesInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('printInvoice')->label(__('billing.print'))->url(fn (): string => route('sales-invoices.print', $this->getRecord()))->openUrlInNewTab()->visible(fn (): bool => $this->getRecord()->status === 'issued'),
            Action::make('downloadXml')->label(__('billing.download_xml'))->url(fn (): string => route('sales-invoices.xml', $this->getRecord()))->visible(fn (): bool => $this->getRecord()->status === 'issued'),
            Action::make('credit')->label(__('billing.credit'))->visible(fn (): bool => $this->getRecord()->status === 'issued' && ! $this->getRecord()->rectifies_invoice_id && SalesInvoiceResource::canCreate())
                ->schema([
                    Select::make('rectifying_series_id')->label(__('billing.fields.invoice_series_id'))->relationship('invoiceSeries', 'name', fn (Builder $query) => $query->where('kind', 'rectifying')->where('environment', $this->getRecord()->environment)->where('is_active', true))->required(),
                    Select::make('type')->label(__('billing.fields.invoice_type'))->options(collect(['R1', 'R2', 'R3', 'R4'])->mapWithKeys(fn (string $type): array => [$type => __('billing.options.'.$type)]))->required(),
                    Textarea::make('reason')->label(__('billing.fields.reason'))->required()->maxLength(500),
                ])->action(function (array $data): void {
                    abort_unless(SalesInvoiceResource::canCreate() && SalesInvoiceResource::canView($this->getRecord()), 403);
                    $credit = app(SalesBilling::class)->credit($this->getRecord(), (int) $data['rectifying_series_id'], $data['type'], $data['reason']);
                    $this->redirect(SalesInvoiceResource::getUrl('edit', ['record' => $credit]));
                }),
            Action::make('sendTest')->label(fn (): string => $this->getRecord()->environment === 'production' ? __('billing.send_production') : __('billing.send'))->requiresConfirmation()
                ->visible(fn (): bool => $this->getRecord()->status === 'issued' && $this->getRecord()->environment === config('billing.environment', 'test') && SalesInvoiceResource::canCreate() && config('billing.send_enabled') && in_array($this->getRecord()->billingRecord?->submission?->status, ['pending', 'transport_error'], true))
                ->action(function (): void {
                    abort_unless(SalesInvoiceResource::canCreate() && SalesInvoiceResource::canView($this->getRecord()), 403);
                    SubmitBillingRecord::dispatch($this->getRecord()->billingRecord->submission->id);
                }),
        ];
    }
}
