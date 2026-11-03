<?php

namespace App\Filament\Resources\PaymentTermInstallments\Pages;

use App\Filament\Resources\PaymentTermInstallments\PaymentTermInstallmentResource;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPaymentTermInstallments extends ListRecords
{
    use HasResizableColumn;

    protected static string $resource = PaymentTermInstallmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
