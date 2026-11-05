<?php

namespace App\Filament\Resources\WorkTimeReversals\Pages;

use App\Filament\Resources\WorkTimeReversals\WorkTimeReversalResource;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageWorkTimeReversals extends ManageRecords
{
    use HasResizableColumn;

    protected static string $resource = WorkTimeReversalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
