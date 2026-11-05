<?php

namespace App\Filament\Resources\WorkProgress\Pages;

use App\Filament\Resources\WorkProgress\WorkProgressResource;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageWorkProgress extends ManageRecords
{
    use HasResizableColumn;

    protected static string $resource = WorkProgressResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
