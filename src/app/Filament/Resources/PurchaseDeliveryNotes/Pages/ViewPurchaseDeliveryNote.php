<?php

namespace App\Filament\Resources\PurchaseDeliveryNotes\Pages;

use App\Filament\Resources\PurchaseDeliveryNotes\PurchaseDeliveryNoteResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchaseDeliveryNote extends ViewRecord
{
    protected static string $resource = PurchaseDeliveryNoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')->label('Imprimir')->url(fn (): string => route('documents.print', ['type' => 'delivery', 'id' => $this->getRecord()->id]))->openUrlInNewTab(),
            EditAction::make(),
        ];
    }
}
