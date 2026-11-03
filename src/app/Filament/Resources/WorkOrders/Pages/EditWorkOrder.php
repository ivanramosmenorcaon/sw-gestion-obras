<?php

namespace App\Filament\Resources\WorkOrders\Pages;

use App\Filament\Actions\BillWorkOrder;
use App\Filament\Resources\WorkOrders\Tables\WorkOrdersTable;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Support\BusinessRecordValidator;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditWorkOrder extends EditRecord
{
    protected static string $resource = WorkOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            BillWorkOrder::make(),
            WorkOrdersTable::treeAction(),
            WorkOrdersTable::budgetAction(),
            DeleteAction::make()
                ->modalDescription('Se eliminará la orden y, si está en borrador, sus partidas, fases y asignaciones. Los capítulos de la obra se conservarán.'),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return BusinessRecordValidator::inForm(fn (): Model => parent::handleRecordUpdate($record, $data), 'data');
    }
}
