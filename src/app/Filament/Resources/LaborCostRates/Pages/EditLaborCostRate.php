<?php

namespace App\Filament\Resources\LaborCostRates\Pages;

use App\Filament\Resources\LaborCostRates\LaborCostRateResource;
use App\Support\BusinessRecordValidator;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditLaborCostRate extends EditRecord
{
    protected static string $resource = LaborCostRateResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return BusinessRecordValidator::inForm(fn (): Model => parent::handleRecordUpdate($record, $data), 'data');
    }
}
