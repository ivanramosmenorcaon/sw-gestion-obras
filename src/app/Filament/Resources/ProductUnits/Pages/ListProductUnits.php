<?php

namespace App\Filament\Resources\ProductUnits\Pages;

use App\Filament\Resources\ProductUnits\ProductUnitResource;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProductUnits extends ListRecords
{
    use HasResizableColumn;

    protected static string $resource = ProductUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
