<?php

namespace App\Filament\Resources\WorkOrderContractorAssignments\Pages;

use App\Filament\Resources\WorkOrderContractorAssignments\WorkOrderContractorAssignmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkOrderContractorAssignments extends ListRecords
{
    protected static string $resource = WorkOrderContractorAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
