<?php

namespace App\Filament\Resources\PurchaseDeliveryNotes\Pages;

use App\Filament\Resources\PurchaseDeliveryNotes\PurchaseDeliveryNoteResource;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPurchaseDeliveryNotes extends ListRecords
{
    use HasResizableColumn;

    protected static string $resource = PurchaseDeliveryNoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
