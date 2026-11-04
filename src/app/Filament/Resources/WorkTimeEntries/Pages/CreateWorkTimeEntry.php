<?php

namespace App\Filament\Resources\WorkTimeEntries\Pages;

use App\Filament\Resources\WorkOrderPhases\WorkOrderPhaseResource;
use App\Filament\Resources\WorkTimeEntries\WorkTimeEntryResource;
use App\Models\WorkOrderPhase;
use App\Support\BusinessRecordValidator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateWorkTimeEntry extends CreateRecord
{
    protected static string $resource = WorkTimeEntryResource::class;

    protected static bool $canCreateAnother = false;

    protected function getRedirectUrl(): string
    {
        return WorkTimeEntryResource::getUrl('edit', ['record' => $this->record]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $phase = WorkOrderPhase::findOrFail($data['work_order_phase_id']);
        abort_unless(WorkOrderPhaseResource::canView($phase) && WorkOrderPhaseResource::canEdit($phase), 403);

        return BusinessRecordValidator::inForm(fn (): Model => parent::handleRecordCreation($data), 'data');
    }
}
