<?php

namespace App\Filament\Resources\PaymentApplications\Pages;

use App\Filament\Resources\PaymentApplications\PaymentApplicationResource;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePaymentApplications extends ManageRecords
{
    use HasResizableColumn;

    protected static string $resource = PaymentApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
