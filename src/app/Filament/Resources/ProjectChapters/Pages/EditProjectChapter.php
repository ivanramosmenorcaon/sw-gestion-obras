<?php

namespace App\Filament\Resources\ProjectChapters\Pages;

use App\Filament\Resources\ProjectChapters\ProjectChapterResource;
use App\Support\BusinessRecordValidator;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditProjectChapter extends EditRecord
{
    protected static string $resource = ProjectChapterResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return BusinessRecordValidator::inForm(fn (): Model => parent::handleRecordUpdate($record, $data), 'data');
    }
}
