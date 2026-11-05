<?php

namespace App\Filament\Resources\ContractorCertificates\Pages;

use App\Filament\Resources\ContractorCertificates\ContractorCertificateResource;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageContractorCertificates extends ManageRecords
{
    use HasResizableColumn;

    protected static string $resource = ContractorCertificateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
