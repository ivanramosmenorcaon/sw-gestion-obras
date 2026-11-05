<?php

namespace App\Filament\Resources\AuditEntries\Pages;

use App\Filament\Resources\AuditEntries\AuditEntryResource;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAuditEntries extends ManageRecords
{
    use HasResizableColumn;

    protected static string $resource = AuditEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
