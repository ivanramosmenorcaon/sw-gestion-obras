<?php

namespace App\Filament\Resources\MaterialConsumptions\Pages;

use App\Filament\Resources\MaterialConsumptions\MaterialConsumptionResource;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMaterialConsumptions extends ListRecords
{
    use HasResizableColumn;

    protected static string $resource = MaterialConsumptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
