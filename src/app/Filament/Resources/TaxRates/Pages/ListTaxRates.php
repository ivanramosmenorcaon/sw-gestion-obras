<?php

namespace App\Filament\Resources\TaxRates\Pages;

use App\Filament\Resources\TaxRates\TaxRateResource;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTaxRates extends ListRecords
{
    use HasResizableColumn;

    protected static string $resource = TaxRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
