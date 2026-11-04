<?php

namespace App\Filament\Resources\PurchaseDeliveryNotes\Schemas;

use App\Filament\Schemas\CostingForms;
use Filament\Schemas\Schema;

class PurchaseDeliveryNoteForm
{
    public static function configure(Schema $schema): Schema
    {
        return CostingForms::configure($schema, 'note');
    }
}
