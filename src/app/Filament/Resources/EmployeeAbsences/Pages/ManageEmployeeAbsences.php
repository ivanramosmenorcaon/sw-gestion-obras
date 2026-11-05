<?php

namespace App\Filament\Resources\EmployeeAbsences\Pages;

use App\Filament\Resources\EmployeeAbsences\EmployeeAbsenceResource;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEmployeeAbsences extends ManageRecords
{
    use HasResizableColumn;

    protected static string $resource = EmployeeAbsenceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
