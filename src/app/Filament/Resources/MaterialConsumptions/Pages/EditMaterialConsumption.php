<?php

namespace App\Filament\Resources\MaterialConsumptions\Pages;

use App\Filament\Actions\CostingActions;
use App\Filament\Resources\MaterialConsumptions\MaterialConsumptionResource;
use App\Support\BusinessRecordValidator;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditMaterialConsumption extends EditRecord
{
    protected static string $resource = MaterialConsumptionResource::class;

    protected function getHeaderActions(): array
    {
        return [CostingActions::confirm(MaterialConsumptionResource::class)->before(fn () => $this->save(shouldRedirect: false)), CostingActions::cancel(MaterialConsumptionResource::class)];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return BusinessRecordValidator::inForm(fn (): Model => parent::handleRecordUpdate($record, $data), 'data');
    }
}
