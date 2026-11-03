<?php

namespace App\Filament\Resources\WorkOrderPhases\Pages;

use App\Filament\Resources\WorkOrderPhases\WorkOrderPhaseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkOrderPhases extends ListRecords
{
    protected static string $resource = WorkOrderPhaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
