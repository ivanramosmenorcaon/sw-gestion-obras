<?php

namespace App\Filament\Resources\MaterialConsumptions\Pages;

use App\Filament\Resources\MaterialConsumptions\MaterialConsumptionResource;
use App\Filament\Resources\WorkOrderPhases\WorkOrderPhaseResource;
use App\Support\ProjectCosting;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;

class ViewMaterialConsumption extends ViewRecord
{
    protected static string $resource = MaterialConsumptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('returnMaterial')->label(__('costing.return'))->requiresConfirmation()
                ->schema([TextInput::make('quantity')->label('Cantidad a devolver (vacío = todo lo pendiente)')->numeric()->minValue('0.000001')->step('0.000001')])
                ->visible(fn (): bool => $this->getRecord()->status === 'posted' && ! $this->getRecord()->reverses_id && MaterialConsumptionResource::canCreate())
                ->action(function (array $data): void {
                    abort_unless(MaterialConsumptionResource::canCreate() && MaterialConsumptionResource::canView($this->getRecord()) && WorkOrderPhaseResource::canEdit($this->getRecord()->workOrderPhase), 403);
                    $returned = app(ProjectCosting::class)->returnMaterial($this->getRecord(), filled($data['quantity'] ?? null) ? (string) $data['quantity'] : null);
                    $this->redirect(MaterialConsumptionResource::getUrl('view', ['record' => $returned]));
                }),
        ];
    }
}
