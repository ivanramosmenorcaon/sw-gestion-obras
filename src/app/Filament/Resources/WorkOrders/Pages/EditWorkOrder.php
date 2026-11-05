<?php

namespace App\Filament\Resources\WorkOrders\Pages;

use App\Filament\Actions\BillWorkOrder;
use App\Filament\Resources\WorkOrders\Tables\WorkOrdersTable;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Support\BusinessRecordValidator;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditWorkOrder extends EditRecord
{
    protected static string $resource = WorkOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')->label('Imprimir')->url(fn (): string => route('documents.print', ['type' => 'order', 'id' => $this->getRecord()->id]))->openUrlInNewTab(),
            BillWorkOrder::make(),
            WorkOrdersTable::treeAction(),
            WorkOrdersTable::budgetAction(),
            WorkOrdersTable::deleteAction(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return BusinessRecordValidator::inForm(fn (): Model => parent::handleRecordUpdate($record, $data), 'data');
    }
}
