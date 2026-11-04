<?php

namespace App\Filament\Resources\MaterialConsumptions\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class MaterialConsumptionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([TextEntry::make('workOrder.reference')->label(__('costing.fields.work_order_id')), TextEntry::make('workOrderPhase.name')->label(__('costing.fields.work_order_phase_id')), TextEntry::make('purchaseDeliveryLine.description')->label(__('costing.fields.description')), TextEntry::make('quantity')->label(__('costing.fields.quantity')), TextEntry::make('consumed_date')->label(__('costing.fields.consumed_date')), TextEntry::make('status')->label(__('costing.fields.status'))->formatStateUsing(fn (string $state): string => __('costing.options.'.$state)), TextEntry::make('total_cost')->label(__('costing.fields.total_cost'))->money('EUR')]);
    }
}
