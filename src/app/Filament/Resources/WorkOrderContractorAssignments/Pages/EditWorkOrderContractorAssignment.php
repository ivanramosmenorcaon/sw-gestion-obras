<?php

namespace App\Filament\Resources\WorkOrderContractorAssignments\Pages;

use App\Filament\Resources\WorkOrderContractorAssignments\WorkOrderContractorAssignmentResource;
use App\Support\BusinessRecordValidator;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditWorkOrderContractorAssignment extends EditRecord
{
    protected static string $resource = WorkOrderContractorAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return BusinessRecordValidator::inForm(fn (): Model => parent::handleRecordUpdate($record, $data), 'data');
    }
}
