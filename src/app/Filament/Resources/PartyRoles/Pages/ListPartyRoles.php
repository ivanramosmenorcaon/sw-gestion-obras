<?php

namespace App\Filament\Resources\PartyRoles\Pages;

use App\Filament\Resources\PartyRoles\PartyRoleResource;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPartyRoles extends ListRecords
{
    use HasResizableColumn;

    protected static string $resource = PartyRoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
