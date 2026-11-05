<?php

namespace App\Filament\Resources\ProjectActivities;

use App\Filament\Clusters\Works\WorksCluster;
use App\Filament\Resources\BusinessResource;
use App\Filament\Resources\ProjectActivities\Pages\ManageProjectActivities;
use App\Filament\Schemas\OperationsForms;
use App\Models\ProjectActivity;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ProjectActivityResource extends BusinessResource
{
    protected static ?string $cluster = WorksCluster::class;

    protected static ?int $navigationSort = 10;

    protected static ?string $model = ProjectActivity::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Notebook;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function getModelLabel(): string
    {
        return OperationsForms::LABELS['ProjectActivity'];
    }

    public static function getPluralModelLabel(): string
    {
        return static::getModelLabel();
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Seguimiento';
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
            'index' => ManageProjectActivities::route('/'),
        ];
    }
}
