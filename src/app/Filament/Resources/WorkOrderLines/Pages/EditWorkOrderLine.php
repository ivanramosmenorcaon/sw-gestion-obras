<?php

namespace App\Filament\Resources\WorkOrderLines\Pages;

use App\Filament\Resources\WorkOrderLines\WorkOrderLineResource;
use App\Support\BusinessRecordValidator;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditWorkOrderLine extends EditRecord
{
    protected static string $resource = WorkOrderLineResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return BusinessRecordValidator::inForm(fn (): Model => parent::handleRecordUpdate($record, $data), 'data');
    }
}
