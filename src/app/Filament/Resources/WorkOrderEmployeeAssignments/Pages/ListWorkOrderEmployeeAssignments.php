<?php

namespace App\Filament\Resources\WorkOrderEmployeeAssignments\Pages;

use App\Filament\Resources\WorkOrderEmployeeAssignments\WorkOrderEmployeeAssignmentResource;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkOrderEmployeeAssignments extends ListRecords
{
    use HasResizableColumn;

    protected static string $resource = WorkOrderEmployeeAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
