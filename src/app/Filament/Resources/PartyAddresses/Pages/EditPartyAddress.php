<?php

namespace App\Filament\Resources\PartyAddresses\Pages;

use App\Filament\Resources\PartyAddresses\PartyAddressResource;
use App\Support\BusinessRecordValidator;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPartyAddress extends EditRecord
{
    protected static string $resource = PartyAddressResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return BusinessRecordValidator::inForm(fn (): Model => parent::handleRecordUpdate($record, $data), 'data');
    }
}
