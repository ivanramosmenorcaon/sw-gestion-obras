<?php

namespace App\Filament\Resources\WorkTimeEntries\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class WorkTimeEntryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([TextEntry::make('workOrder.reference')->label(__('costing.fields.work_order_id')), TextEntry::make('workOrderPhase.name')->label(__('costing.fields.work_order_phase_id')), TextEntry::make('employee.name')->label(__('costing.fields.employee_id')), TextEntry::make('contractor.legal_name')->label(__('costing.fields.contractor_id')), TextEntry::make('started_at')->label(__('costing.fields.started_at')), TextEntry::make('ended_at')->label(__('costing.fields.ended_at')), TextEntry::make('hours')->label(__('costing.fields.hours')), TextEntry::make('total_cost')->label(__('costing.fields.total_cost'))->money('EUR'), TextEntry::make('status')->label(__('costing.fields.status'))->formatStateUsing(fn (string $state): string => __('costing.options.'.$state))]);
    }
}
