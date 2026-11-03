<?php

namespace App\Filament\Resources\PaymentTermInstallments\Pages;

use App\Filament\Resources\PaymentTermInstallments\PaymentTermInstallmentResource;
use App\Support\BusinessRecordValidator;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPaymentTermInstallment extends EditRecord
{
    protected static string $resource = PaymentTermInstallmentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return BusinessRecordValidator::inForm(fn (): Model => parent::handleRecordUpdate($record, $data), 'data');
    }
}
