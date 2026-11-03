<?php

namespace App\Filament\Resources\PaymentTerms\Pages;

use App\Filament\Resources\PaymentTerms\PaymentTermResource;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPaymentTerms extends ListRecords
{
    use HasResizableColumn;

    protected static string $resource = PaymentTermResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
