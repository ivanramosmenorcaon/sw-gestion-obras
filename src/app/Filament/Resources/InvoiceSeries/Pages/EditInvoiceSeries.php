<?php

namespace App\Filament\Resources\InvoiceSeries\Pages;

use App\Filament\Resources\InvoiceSeries\InvoiceSeriesResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInvoiceSeries extends EditRecord
{
    protected static string $resource = InvoiceSeriesResource::class;

    protected function getHeaderActions(): array { return []; }
}
