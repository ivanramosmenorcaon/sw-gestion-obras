<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Actions\ImportProjectMeasurements;
use App\Filament\Resources\Projects\ProjectResource;
use App\Support\BusinessRecordValidator;
use App\Support\ProjectCostReport;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('projectCosts')->label('Costes y gráficas')
                ->visible(fn (): bool => ProjectCostReport::canView($this->getRecord()))
                ->url(fn (): string => ProjectResource::getUrl('costs', ['record' => $this->getRecord()])),
            ImportProjectMeasurements::make($this->getRecord()),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return BusinessRecordValidator::inForm(fn (): Model => parent::handleRecordUpdate($record, $data), 'data');
    }
}
