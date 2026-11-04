<?php

namespace App\Filament\Resources\WorkTimeEntries\Pages;

use App\Filament\Resources\WorkTimeEntries\WorkTimeEntryResource;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkTimeEntries extends ListRecords
{
    use HasResizableColumn;

    protected static string $resource = WorkTimeEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
