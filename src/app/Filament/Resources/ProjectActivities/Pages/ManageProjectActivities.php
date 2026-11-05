<?php

namespace App\Filament\Resources\ProjectActivities\Pages;

use App\Filament\Resources\ProjectActivities\ProjectActivityResource;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageProjectActivities extends ManageRecords
{
    use HasResizableColumn;

    protected static string $resource = ProjectActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
