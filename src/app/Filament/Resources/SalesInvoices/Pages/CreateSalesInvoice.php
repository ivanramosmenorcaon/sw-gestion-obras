<?php

namespace App\Filament\Resources\SalesInvoices\Pages;

use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSalesInvoice extends CreateRecord
{
    protected static string $resource = SalesInvoiceResource::class;
    protected static bool $canCreateAnother = false;
    protected function getRedirectUrl(): string { return SalesInvoiceResource::getUrl('edit', ['record' => $this->record]); }
}
