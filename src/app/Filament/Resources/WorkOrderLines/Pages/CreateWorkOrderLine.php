<?php

namespace App\Filament\Resources\WorkOrderLines\Pages;

use App\Filament\Resources\WorkOrderLines\WorkOrderLineResource;
use App\Support\BusinessRecordValidator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateWorkOrderLine extends CreateRecord
{
    protected static string $resource = WorkOrderLineResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return BusinessRecordValidator::inForm(fn (): Model => parent::handleRecordCreation($data), 'data');
    }
}
