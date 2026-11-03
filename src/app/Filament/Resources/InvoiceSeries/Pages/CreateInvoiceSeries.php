<?php

namespace App\Filament\Resources\InvoiceSeries\Pages;

use App\Filament\Resources\InvoiceSeries\InvoiceSeriesResource;
use App\Support\BusinessRecordValidator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateInvoiceSeries extends CreateRecord
{
    protected static string $resource = InvoiceSeriesResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return BusinessRecordValidator::inForm(fn (): Model => parent::handleRecordCreation($data), 'data');
    }
}
