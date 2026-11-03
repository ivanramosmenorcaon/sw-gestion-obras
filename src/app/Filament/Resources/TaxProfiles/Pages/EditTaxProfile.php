<?php

namespace App\Filament\Resources\TaxProfiles\Pages;

use App\Filament\Resources\TaxProfiles\TaxProfileResource;
use App\Support\BusinessRecordValidator;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditTaxProfile extends EditRecord
{
    protected static string $resource = TaxProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return BusinessRecordValidator::inForm(fn (): Model => parent::handleRecordUpdate($record, $data), 'data');
    }
}
