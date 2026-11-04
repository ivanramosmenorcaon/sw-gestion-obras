<?php

namespace App\Filament\Resources\WorkTimeEntries\Schemas;

use App\Filament\Schemas\CostingForms;
use Filament\Schemas\Schema;

class WorkTimeEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return CostingForms::configure($schema, 'time');
    }
}
