<?php

namespace App\Filament\Resources\LaborCostRates\Pages;

use App\Filament\Resources\LaborCostRates\LaborCostRateResource;
use App\Support\BusinessRecordValidator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateLaborCostRate extends CreateRecord
{
    protected static string $resource = LaborCostRateResource::class;

    protected static bool $canCreateAnother = false;

    protected function getRedirectUrl(): string
    {
        return LaborCostRateResource::getUrl('edit', ['record' => $this->record]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        return BusinessRecordValidator::inForm(fn (): Model => parent::handleRecordCreation($data), 'data');
    }
}
