<?php

namespace App\Filament\Resources\WorkTimeEntries\Pages;

use App\Filament\Actions\CostingActions;
use App\Filament\Resources\WorkTimeEntries\WorkTimeEntryResource;
use App\Support\BusinessRecordValidator;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditWorkTimeEntry extends EditRecord
{
    protected static string $resource = WorkTimeEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [CostingActions::confirm(WorkTimeEntryResource::class)->before(fn () => $this->save(shouldRedirect: false)), CostingActions::cancel(WorkTimeEntryResource::class)];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return BusinessRecordValidator::inForm(fn (): Model => parent::handleRecordUpdate($record, $data), 'data');
    }
}
