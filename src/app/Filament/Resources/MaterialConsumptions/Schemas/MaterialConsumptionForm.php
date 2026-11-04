<?php

namespace App\Filament\Resources\MaterialConsumptions\Schemas;

use App\Filament\Schemas\CostingForms;
use Filament\Schemas\Schema;

class MaterialConsumptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return CostingForms::configure($schema, 'consumption');
    }
}
