<?php

namespace App\Filament\Resources\WorkTimeEntries\Pages;

use App\Filament\Resources\WorkTimeEntries\WorkTimeEntryResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWorkTimeEntry extends ViewRecord
{
    protected static string $resource = WorkTimeEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')->label('Imprimir')->url(fn (): string => route('documents.print', ['type' => 'time', 'id' => $this->getRecord()->id]))->openUrlInNewTab(),
            EditAction::make(),
        ];
    }
}
