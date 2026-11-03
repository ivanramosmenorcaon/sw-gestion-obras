<?php

namespace App\Filament\Resources\ProjectChapters\Pages;

use App\Filament\Resources\ProjectChapters\ProjectChapterResource;
use App\Support\BusinessRecordValidator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProjectChapter extends CreateRecord
{
    protected static string $resource = ProjectChapterResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return BusinessRecordValidator::inForm(fn (): Model => parent::handleRecordCreation($data), 'data');
    }
}
