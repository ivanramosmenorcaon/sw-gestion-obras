<?php

namespace App\Filament\Resources\InvoiceSeries\Pages;

use App\Filament\Resources\InvoiceSeries\InvoiceSeriesResource;
use App\Support\BusinessRecordValidator;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditInvoiceSeries extends EditRecord
{
    protected static string $resource = InvoiceSeriesResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return BusinessRecordValidator::inForm(fn (): Model => parent::handleRecordUpdate($record, $data), 'data');
    }
}
