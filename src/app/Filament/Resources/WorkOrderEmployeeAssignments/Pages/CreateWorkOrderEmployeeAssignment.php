<?php

namespace App\Filament\Resources\WorkOrderEmployeeAssignments\Pages;

use App\Filament\Resources\WorkOrderEmployeeAssignments\WorkOrderEmployeeAssignmentResource;
use App\Support\BusinessRecordValidator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateWorkOrderEmployeeAssignment extends CreateRecord
{
    protected static string $resource = WorkOrderEmployeeAssignmentResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return BusinessRecordValidator::inForm(fn (): Model => parent::handleRecordCreation($data), 'data');
    }
}
