<?php

namespace App\Filament\Resources\PurchaseDeliveryNotes\Pages;

use App\Filament\Resources\PurchaseDeliveryNotes\PurchaseDeliveryNoteResource;
use App\Support\BusinessRecordValidator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePurchaseDeliveryNote extends CreateRecord
{
    protected static string $resource = PurchaseDeliveryNoteResource::class;

    protected static bool $canCreateAnother = false;

    protected function getRedirectUrl(): string
    {
        return PurchaseDeliveryNoteResource::getUrl('edit', ['record' => $this->record]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        return BusinessRecordValidator::inForm(fn (): Model => parent::handleRecordCreation($data), 'data');
    }
}
