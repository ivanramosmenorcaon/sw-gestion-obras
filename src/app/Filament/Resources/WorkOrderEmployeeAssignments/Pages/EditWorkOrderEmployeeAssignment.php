<?php

namespace App\Filament\Resources\WorkOrderEmployeeAssignments\Pages;

use App\Filament\Resources\WorkOrderEmployeeAssignments\WorkOrderEmployeeAssignmentResource;
use App\Support\BusinessRecordValidator;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditWorkOrderEmployeeAssignment extends EditRecord
{
    protected static string $resource = WorkOrderEmployeeAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return BusinessRecordValidator::inForm(fn (): Model => parent::handleRecordUpdate($record, $data), 'data');
    }
}
