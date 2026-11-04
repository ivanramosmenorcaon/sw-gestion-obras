<?php

namespace App\Filament\Resources\Warehouses\Schemas;

use App\Filament\Schemas\CostingForms;
use Filament\Schemas\Schema;

class WarehouseForm
{
    public static function configure(Schema $schema): Schema
    {
        return CostingForms::configure($schema, 'warehouse');
    }
}
