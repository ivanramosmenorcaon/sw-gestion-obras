<?php

namespace App\Filament\Resources\WorkOrderContractorAssignments\Pages;

use App\Filament\Resources\WorkOrderContractorAssignments\WorkOrderContractorAssignmentResource;
use App\Support\BusinessRecordValidator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateWorkOrderContractorAssignment extends CreateRecord
{
    protected static string $resource = WorkOrderContractorAssignmentResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return BusinessRecordValidator::inForm(fn (): Model => parent::handleRecordCreation($data), 'data');
    }
}
