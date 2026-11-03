<?php

namespace App\Filament\Resources\WorkOrderPhases\Pages;

use App\Filament\Resources\WorkOrderPhases\WorkOrderPhaseResource;
use App\Support\BusinessRecordValidator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateWorkOrderPhase extends CreateRecord
{
    protected static string $resource = WorkOrderPhaseResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return BusinessRecordValidator::inForm(fn (): Model => parent::handleRecordCreation($data), 'data');
    }
}
