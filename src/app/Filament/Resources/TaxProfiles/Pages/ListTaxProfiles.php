<?php

namespace App\Filament\Resources\TaxProfiles\Pages;

use App\Filament\Resources\TaxProfiles\TaxProfileResource;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTaxProfiles extends ListRecords
{
    use HasResizableColumn;

    protected static string $resource = TaxProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
