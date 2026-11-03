<?php

namespace App\Filament\Resources\SalesInvoices\Pages;

use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use Filament\Actions\CreateAction;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Resources\Pages\ListRecords;

class ListSalesInvoices extends ListRecords
{
    use HasResizableColumn;

    protected static string $resource = SalesInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
