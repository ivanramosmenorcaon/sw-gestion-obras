<?php

namespace App\Filament\Resources\UnitConversions\Pages;

use App\Filament\Resources\UnitConversions\UnitConversionResource;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUnitConversions extends ListRecords
{
    use HasResizableColumn;

    protected static string $resource = UnitConversionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
