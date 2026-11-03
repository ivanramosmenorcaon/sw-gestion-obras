<?php

namespace App\Filament\Resources\ProductUnits\Pages;

use App\Filament\Resources\ProductUnits\ProductUnitResource;
use App\Support\BusinessRecordValidator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProductUnit extends CreateRecord
{
    protected static string $resource = ProductUnitResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return BusinessRecordValidator::inForm(fn (): Model => parent::handleRecordCreation($data), 'data');
    }
}
