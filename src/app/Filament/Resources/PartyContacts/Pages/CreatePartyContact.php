<?php

namespace App\Filament\Resources\PartyContacts\Pages;

use App\Filament\Resources\PartyContacts\PartyContactResource;
use App\Support\BusinessRecordValidator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePartyContact extends CreateRecord
{
    protected static string $resource = PartyContactResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return BusinessRecordValidator::inForm(fn (): Model => parent::handleRecordCreation($data), 'data');
    }
}
