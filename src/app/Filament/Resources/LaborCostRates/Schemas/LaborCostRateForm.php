<?php

namespace App\Filament\Resources\LaborCostRates\Schemas;

use App\Filament\Schemas\CostingForms;
use Filament\Schemas\Schema;

class LaborCostRateForm
{
    public static function configure(Schema $schema): Schema
    {
        return CostingForms::configure($schema, 'rate');
    }
}
