<?php

namespace App\Filament\Resources\InvoiceMaturities\Pages;

use App\Filament\Resources\InvoiceMaturities\InvoiceMaturityResource;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageInvoiceMaturities extends ManageRecords
{
    use HasResizableColumn;

    protected static string $resource = InvoiceMaturityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
