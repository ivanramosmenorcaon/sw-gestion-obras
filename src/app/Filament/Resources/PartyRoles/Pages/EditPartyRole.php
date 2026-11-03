<?php

namespace App\Filament\Resources\PartyRoles\Pages;

use App\Filament\Resources\PartyRoles\PartyRoleResource;
use App\Support\BusinessRecordValidator;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPartyRole extends EditRecord
{
    protected static string $resource = PartyRoleResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return BusinessRecordValidator::inForm(fn (): Model => parent::handleRecordUpdate($record, $data), 'data');
    }
}
