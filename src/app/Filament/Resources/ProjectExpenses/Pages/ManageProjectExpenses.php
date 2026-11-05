<?php

namespace App\Filament\Resources\ProjectExpenses\Pages;

use App\Filament\Resources\ProjectExpenses\ProjectExpenseResource;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageProjectExpenses extends ManageRecords
{
    use HasResizableColumn;

    protected static string $resource = ProjectExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
