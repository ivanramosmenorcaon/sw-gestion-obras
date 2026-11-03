<?php

namespace App\Filament\Resources\InvoiceSeries\Pages;

use App\Filament\Resources\InvoiceSeries\InvoiceSeriesResource;
use Filament\Actions\CreateAction;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Resources\Pages\ListRecords;

class ListInvoiceSeries extends ListRecords
{
    use HasResizableColumn;

    protected static string $resource = InvoiceSeriesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
