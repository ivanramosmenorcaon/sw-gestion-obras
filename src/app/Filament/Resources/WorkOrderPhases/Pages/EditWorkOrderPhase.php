<?php

namespace App\Filament\Resources\WorkOrderPhases\Pages;

use App\Filament\Resources\WorkOrderPhases\WorkOrderPhaseResource;
use App\Support\BusinessRecordValidator;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditWorkOrderPhase extends EditRecord
{
    protected static string $resource = WorkOrderPhaseResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return BusinessRecordValidator::inForm(fn (): Model => parent::handleRecordUpdate($record, $data), 'data');
    }
}
