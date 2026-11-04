<?php

namespace App\Filament\Resources\WorkTimeEntries\Pages;

use App\Filament\Resources\WorkTimeEntries\WorkTimeEntryResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWorkTimeEntry extends ViewRecord
{
    protected static string $resource = WorkTimeEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
