<?php

namespace App\Filament\Resources\LaborCostRates\Pages;

use App\Filament\Resources\LaborCostRates\LaborCostRateResource;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLaborCostRates extends ListRecords
{
    use HasResizableColumn;

    protected static string $resource = LaborCostRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
