<?php

namespace App\Filament\Resources\PaymentTermInstallments\Pages;

use App\Filament\Resources\PaymentTermInstallments\PaymentTermInstallmentResource;
use App\Support\BusinessRecordValidator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePaymentTermInstallment extends CreateRecord
{
    protected static string $resource = PaymentTermInstallmentResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return BusinessRecordValidator::inForm(fn (): Model => parent::handleRecordCreation($data), 'data');
    }
}
