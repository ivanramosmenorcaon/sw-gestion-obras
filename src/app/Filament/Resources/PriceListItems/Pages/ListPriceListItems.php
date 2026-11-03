<?php

namespace App\Filament\Resources\PriceListItems\Pages;

use App\Filament\Resources\PriceListItems\PriceListItemResource;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPriceListItems extends ListRecords
{
    use HasResizableColumn;

    protected static string $resource = PriceListItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
