<?php

namespace App\Filament\Resources\PartyAddresses\Pages;

use App\Filament\Resources\PartyAddresses\PartyAddressResource;
use App\Support\BusinessRecordValidator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePartyAddress extends CreateRecord
{
    protected static string $resource = PartyAddressResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return BusinessRecordValidator::inForm(fn (): Model => parent::handleRecordCreation($data), 'data');
    }
}
