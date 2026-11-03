<?php

namespace App\Filament\Resources\SupplierProducts\Pages;

use App\Filament\Resources\SupplierProducts\SupplierProductResource;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSupplierProducts extends ListRecords
{
    use HasResizableColumn;

    protected static string $resource = SupplierProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
