<?php

namespace App\Filament\Resources\WorkOrderPhases\Pages;

use App\Filament\Resources\WorkOrderPhases\WorkOrderPhaseResource;
use App\Support\BusinessRecordValidator;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditWorkOrderPhase extends EditRecord
{
    protected static string $resource = WorkOrderPhaseResource::class;

    protected function getHeaderActions(): array
    {
        return [Action::make('print')->label('Imprimir')->url(fn (): string => route('documents.print', ['type' => 'phase', 'id' => $this->getRecord()->id]))->openUrlInNewTab()];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return BusinessRecordValidator::inForm(fn (): Model => parent::handleRecordUpdate($record, $data), 'data');
    }
}
