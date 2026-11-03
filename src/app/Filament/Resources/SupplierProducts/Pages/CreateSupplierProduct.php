<?php

namespace App\Filament\Resources\SupplierProducts\Pages;

use App\Filament\Resources\SupplierProducts\SupplierProductResource;
use App\Support\BusinessRecordValidator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSupplierProduct extends CreateRecord
{
    protected static string $resource = SupplierProductResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return BusinessRecordValidator::inForm(fn (): Model => parent::handleRecordCreation($data), 'data');
    }
}
