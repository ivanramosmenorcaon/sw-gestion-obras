<?php

namespace App\Filament\Resources\WorkOrderEmployeeAssignments\Pages;

use App\Filament\Resources\WorkOrderEmployeeAssignments\WorkOrderEmployeeAssignmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkOrderEmployeeAssignments extends ListRecords
{
    protected static string $resource = WorkOrderEmployeeAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
