<?php

namespace App\Filament\Resources\TaxRates\Pages;

use App\Filament\Resources\TaxRates\TaxRateResource;
use App\Support\BusinessRecordValidator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTaxRate extends CreateRecord
{
    protected static string $resource = TaxRateResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return BusinessRecordValidator::inForm(fn (): Model => parent::handleRecordCreation($data), 'data');
    }
}
