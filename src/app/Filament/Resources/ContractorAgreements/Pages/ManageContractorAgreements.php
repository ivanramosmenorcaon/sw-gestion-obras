<?php

namespace App\Filament\Resources\ContractorAgreements\Pages;

use App\Filament\Resources\ContractorAgreements\ContractorAgreementResource;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageContractorAgreements extends ManageRecords
{
    use HasResizableColumn;

    protected static string $resource = ContractorAgreementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
