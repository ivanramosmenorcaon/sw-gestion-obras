<?php

namespace App\Filament\Resources\PurchaseDeliveryNotes\Pages;

use App\Filament\Actions\CostingActions;
use App\Filament\Resources\PurchaseDeliveryNotes\PurchaseDeliveryNoteResource;
use App\Support\BusinessRecordValidator;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPurchaseDeliveryNote extends EditRecord
{
    protected static string $resource = PurchaseDeliveryNoteResource::class;

    protected function getHeaderActions(): array
    {
        return [CostingActions::confirm(PurchaseDeliveryNoteResource::class)->before(fn () => $this->save(shouldRedirect: false)), CostingActions::cancel(PurchaseDeliveryNoteResource::class)];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return BusinessRecordValidator::inForm(fn (): Model => parent::handleRecordUpdate($record, $data), 'data');
    }
}
