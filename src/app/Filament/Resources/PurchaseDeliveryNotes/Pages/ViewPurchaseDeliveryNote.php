<?php

namespace App\Filament\Resources\PurchaseDeliveryNotes\Pages;

use App\Filament\Resources\PurchaseDeliveryNotes\PurchaseDeliveryNoteResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchaseDeliveryNote extends ViewRecord
{
    protected static string $resource = PurchaseDeliveryNoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
