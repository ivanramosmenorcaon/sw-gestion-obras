<?php

namespace App\Filament\Resources\Warehouses\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class WarehouseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([TextEntry::make('code')->label(__('costing.fields.code')), TextEntry::make('name')->label(__('costing.fields.name')), TextEntry::make('is_active')->label(__('costing.fields.is_active'))]);
    }
}
