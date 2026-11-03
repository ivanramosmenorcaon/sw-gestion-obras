<?php

namespace App\Filament\Resources\PartyAddresses\Pages;

use App\Filament\Resources\PartyAddresses\PartyAddressResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPartyAddresses extends ListRecords
{
    protected static string $resource = PartyAddressResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
