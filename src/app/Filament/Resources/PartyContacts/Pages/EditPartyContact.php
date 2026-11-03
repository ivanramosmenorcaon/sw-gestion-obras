<?php

namespace App\Filament\Resources\PartyContacts\Pages;

use App\Filament\Resources\PartyContacts\PartyContactResource;
use App\Support\BusinessRecordValidator;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPartyContact extends EditRecord
{
    protected static string $resource = PartyContactResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return BusinessRecordValidator::inForm(fn (): Model => parent::handleRecordUpdate($record, $data), 'data');
    }
}
