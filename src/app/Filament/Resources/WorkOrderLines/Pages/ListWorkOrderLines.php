<?php

namespace App\Filament\Resources\WorkOrderLines\Pages;

use App\Filament\Resources\WorkOrderLines\WorkOrderLineResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkOrderLines extends ListRecords
{
    protected static string $resource = WorkOrderLineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
