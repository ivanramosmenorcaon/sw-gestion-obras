<?php

namespace App\Filament\Resources\Budgets\Pages;

use App\Filament\Resources\Budgets\BudgetResource;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Resources\Pages\ListRecords;

class ListBudgets extends ListRecords
{
    use HasResizableColumn;

    protected static string $resource = BudgetResource::class;

    public function getSubheading(): ?string
    {
        return 'Para crear un presupuesto, abra una orden de trabajo y pulse «Crear presupuesto».';
    }
}
