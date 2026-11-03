<?php

namespace App\Filament\Resources\PartyAddresses\Pages;

use App\Filament\Resources\PartyAddresses\PartyAddressResource;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPartyAddresses extends ListRecords
{
    use HasResizableColumn;

    protected static string $resource = PartyAddressResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
