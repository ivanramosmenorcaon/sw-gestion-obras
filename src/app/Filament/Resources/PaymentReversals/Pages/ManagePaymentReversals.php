<?php

namespace App\Filament\Resources\PaymentReversals\Pages;

use App\Filament\Resources\PaymentReversals\PaymentReversalResource;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePaymentReversals extends ManageRecords
{
    use HasResizableColumn;

    protected static string $resource = PaymentReversalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
