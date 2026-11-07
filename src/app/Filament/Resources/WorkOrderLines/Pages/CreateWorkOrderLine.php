<?php

namespace App\Filament\Resources\WorkOrderLines\Pages;

use App\Filament\Resources\WorkOrderLines\WorkOrderLineResource;
use App\Support\BusinessRecordValidator;
use App\Support\ProjectScope;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateWorkOrderLine extends CreateRecord
{
    protected static string $resource = WorkOrderLineResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return BusinessRecordValidator::inForm(fn (): Model => DB::transaction(function () use ($data): Model {
            $line = parent::handleRecordCreation($data);
            app(ProjectScope::class)->captureLine($line);

            return $line;
        }), 'data');
    }
}
