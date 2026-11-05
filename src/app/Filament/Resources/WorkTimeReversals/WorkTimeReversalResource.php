<?php

namespace App\Filament\Resources\WorkTimeReversals;

use App\Filament\Resources\BusinessResource;
use App\Filament\Resources\WorkTimeReversals\Pages\ManageWorkTimeReversals;
use App\Filament\Schemas\OperationsForms;
use App\Models\WorkTimeReversal;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class WorkTimeReversalResource extends BusinessResource
{
    protected static ?string $model = WorkTimeReversal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function getModelLabel(): string
    {
        return OperationsForms::LABELS['WorkTimeReversal'];
    }

    public static function getPluralModelLabel(): string
    {
        return static::getModelLabel();
    }

    public static function getNavigationGroup(): string
    {
        return 'Obras';
    }

    public static function canEdit(Model $record): bool
    {
        return $record->status === 'draft' && parent::canEdit($record);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return OperationsForms::form($schema, static::getModel());
    }

    public static function table(Table $table): Table
    {
        return OperationsForms::table($table, static::getModel());
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageWorkTimeReversals::route('/'),
        ];
    }
}
