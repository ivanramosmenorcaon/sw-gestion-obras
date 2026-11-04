<?php

namespace App\Filament\Resources\WorkTimeEntries;

use App\Filament\Resources\WorkOrderPhases\WorkOrderPhaseResource;
use App\Filament\Resources\WorkTimeEntries\Pages\CreateWorkTimeEntry;
use App\Filament\Resources\WorkTimeEntries\Pages\EditWorkTimeEntry;
use App\Filament\Resources\WorkTimeEntries\Pages\ListWorkTimeEntries;
use App\Filament\Resources\WorkTimeEntries\Pages\ViewWorkTimeEntry;
use App\Filament\Resources\WorkTimeEntries\Schemas\WorkTimeEntryForm;
use App\Filament\Resources\WorkTimeEntries\Schemas\WorkTimeEntryInfolist;
use App\Filament\Resources\WorkTimeEntries\Tables\WorkTimeEntriesTable;
use App\Models\WorkTimeEntry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class WorkTimeEntryResource extends Resource
{
    protected static ?string $model = WorkTimeEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'description';

    public static function getModelLabel(): string
    {
        return __('costing.time');
    }

    public static function getPluralModelLabel(): string
    {
        return __('costing.times');
    }

    public static function getNavigationGroup(): string
    {
        return __('business.groups.works');
    }

    public static function canEdit(Model $record): bool
    {
        return $record->status === 'draft' && WorkOrderPhaseResource::canEdit($record->workOrderPhase) && parent::canEdit($record);
    }

    public static function canView(Model $record): bool
    {
        return WorkOrderPhaseResource::canView($record->workOrderPhase) && parent::canView($record);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return WorkTimeEntryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WorkTimeEntryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkTimeEntriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkTimeEntries::route('/'),
            'create' => CreateWorkTimeEntry::route('/create'),
            'view' => ViewWorkTimeEntry::route('/{record}'),
            'edit' => EditWorkTimeEntry::route('/{record}/edit'),
        ];
    }
}
