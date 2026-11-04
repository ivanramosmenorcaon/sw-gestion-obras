<?php

namespace App\Filament\Resources\LaborCostRates\Pages;

use App\Filament\Resources\LaborCostRates\LaborCostRateResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewLaborCostRate extends ViewRecord
{
    protected static string $resource = LaborCostRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
