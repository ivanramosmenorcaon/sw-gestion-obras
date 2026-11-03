<?php

namespace App\Filament\Resources\ProjectChapters\Pages;

use App\Filament\Resources\ProjectChapters\ProjectChapterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProjectChapters extends ListRecords
{
    protected static string $resource = ProjectChapterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
