<?php

namespace App\Filament\Resources\ProjectItems\Pages;

use App\Filament\Resources\ProjectItems\ProjectItemResource;
use App\Models\ProjectItem;
use App\Support\BusinessRecordValidator;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Model;

class ManageProjectItems extends ManageRecords
{
    use HasResizableColumn;

    protected static string $resource = ProjectItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(fn (array $data): Model => BusinessRecordValidator::inForm(fn (): Model => ProjectItem::create($data), 'mountedActions.0.data')),
        ];
    }
}
