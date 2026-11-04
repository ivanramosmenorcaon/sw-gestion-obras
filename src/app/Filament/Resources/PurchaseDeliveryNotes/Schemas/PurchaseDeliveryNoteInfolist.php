<?php

namespace App\Filament\Resources\PurchaseDeliveryNotes\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PurchaseDeliveryNoteInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([TextEntry::make('reference')->label(__('costing.fields.reference')), TextEntry::make('supplier.legal_name')->label(__('costing.fields.supplier_id')), TextEntry::make('warehouse.name')->label(__('costing.fields.warehouse_id')), TextEntry::make('received_date')->label(__('costing.fields.received_date')), TextEntry::make('status')->label(__('costing.fields.status'))->formatStateUsing(fn (string $state): string => __('costing.options.'.$state)), TextEntry::make('net_total')->label(__('costing.fields.net_total'))->money('EUR')]);
    }
}
