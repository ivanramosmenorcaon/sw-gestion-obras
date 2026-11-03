<?php

namespace App\Filament\Resources\WorkOrderLines\Pages;

use App\Filament\Resources\WorkOrderLines\WorkOrderLineResource;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkOrderLines extends ListRecords
{
    use HasResizableColumn;

    protected static string $resource = WorkOrderLineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
