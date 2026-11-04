<?php

namespace App\Filament\Resources\LaborCostRates\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class LaborCostRateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([TextEntry::make('employee.name')->label(__('costing.fields.employee_id')), TextEntry::make('contractor.legal_name')->label(__('costing.fields.contractor_id')), TextEntry::make('hourly_cost')->label(__('costing.fields.hourly_cost'))->money('EUR'), TextEntry::make('valid_from')->label(__('costing.fields.valid_from')), TextEntry::make('valid_until')->label(__('costing.fields.valid_until'))]);
    }
}
