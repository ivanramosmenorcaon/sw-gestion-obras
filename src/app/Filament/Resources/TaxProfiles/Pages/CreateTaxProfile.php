<?php

namespace App\Filament\Resources\TaxProfiles\Pages;

use App\Filament\Resources\TaxProfiles\TaxProfileResource;
use App\Support\BusinessRecordValidator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTaxProfile extends CreateRecord
{
    protected static string $resource = TaxProfileResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return BusinessRecordValidator::inForm(fn (): Model => parent::handleRecordCreation($data), 'data');
    }
}
