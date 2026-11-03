<?php

namespace App\Filament\Resources\Parties\Pages;

use App\Filament\Resources\Parties\PartyResource;
use App\Support\BusinessRecordValidator;
use App\Support\FiscalIdentity;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditParty extends EditRecord
{
    protected static string $resource = PartyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('checkFiscalIdentity')
                ->label(__('fiscal.messages.identity_check'))
                ->tooltip(__('fiscal.messages.identity_help'))
                ->action(function (): void {
                    BusinessRecordValidator::inForm(fn (): array => FiscalIdentity::recipient($this->getRecord()), 'data');
                    Notification::make()->success()->title(__('fiscal.messages.identity_valid'))->send();
                }),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return BusinessRecordValidator::inForm(fn (): Model => parent::handleRecordUpdate($record, $data), 'data');
    }
}
