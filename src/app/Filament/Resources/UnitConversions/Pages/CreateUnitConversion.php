<?php

namespace App\Filament\Resources\UnitConversions\Pages;

use App\Filament\Resources\UnitConversions\UnitConversionResource;
use App\Support\BusinessRecordValidator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUnitConversion extends CreateRecord
{
    protected static string $resource = UnitConversionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return BusinessRecordValidator::inForm(fn (): Model => parent::handleRecordCreation($data), 'data');
    }
}
