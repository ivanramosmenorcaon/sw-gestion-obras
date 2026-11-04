<?php

namespace App\Filament\Resources\MaterialConsumptions\Pages;

use App\Filament\Resources\MaterialConsumptions\MaterialConsumptionResource;
use App\Filament\Resources\WorkOrderPhases\WorkOrderPhaseResource;
use App\Models\WorkOrderPhase;
use App\Support\BusinessRecordValidator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateMaterialConsumption extends CreateRecord
{
    protected static string $resource = MaterialConsumptionResource::class;

    protected static bool $canCreateAnother = false;

    protected function getRedirectUrl(): string
    {
        return MaterialConsumptionResource::getUrl('edit', ['record' => $this->record]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $phase = WorkOrderPhase::findOrFail($data['work_order_phase_id']);
        abort_unless(WorkOrderPhaseResource::canView($phase) && WorkOrderPhaseResource::canEdit($phase), 403);

        return BusinessRecordValidator::inForm(fn (): Model => parent::handleRecordCreation($data), 'data');
    }
}
