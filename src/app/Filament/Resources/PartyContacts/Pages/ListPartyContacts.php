<?php

namespace App\Filament\Resources\PartyContacts\Pages;

use App\Filament\Resources\PartyContacts\PartyContactResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPartyContacts extends ListRecords
{
    protected static string $resource = PartyContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
