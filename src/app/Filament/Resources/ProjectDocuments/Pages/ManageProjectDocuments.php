<?php

namespace App\Filament\Resources\ProjectDocuments\Pages;

use App\Filament\Resources\ProjectDocuments\ProjectDocumentResource;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageProjectDocuments extends ManageRecords
{
    use HasResizableColumn;

    protected static string $resource = ProjectDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
