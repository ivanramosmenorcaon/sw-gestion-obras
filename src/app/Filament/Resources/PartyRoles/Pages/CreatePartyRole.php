<?php

namespace App\Filament\Resources\PartyRoles\Pages;

use App\Filament\Resources\PartyRoles\PartyRoleResource;
use App\Support\BusinessRecordValidator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePartyRole extends CreateRecord
{
    protected static string $resource = PartyRoleResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return BusinessRecordValidator::inForm(fn (): Model => parent::handleRecordCreation($data), 'data');
    }
}
