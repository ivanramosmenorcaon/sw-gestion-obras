<?php

namespace App\Filament\Resources\Projects;

use App\Filament\Clusters\Works\WorksCluster;
use App\Filament\Resources\BusinessResource;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Resources\Projects\Pages\ProjectCosts;
use App\Filament\Resources\Projects\Schemas\ProjectForm;
use App\Filament\Resources\Projects\Tables\ProjectsTable;
use App\Filament\Resources\Projects\Widgets\ProjectCostBreakdownChart;
use App\Filament\Resources\Projects\Widgets\ProjectCostHistoryChart;
use App\Filament\Resources\Projects\Widgets\ProjectCostStats;
use App\Models\Project;
use App\Support\BusinessRecordValidator;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ProjectResource extends BusinessResource
{
    protected static ?string $cluster = WorksCluster::class;

    protected static ?int $navigationSort = 1;

    protected static ?string $model = Project::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Crane;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return 'Preparación';
    }

    public static function getModelLabel(): string
    {
        return __('business.models.projects.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('business.models.projects.plural');
    }

    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record) && BusinessRecordValidator::canDelete($record);
    }

    public static function form(Schema $schema): Schema
    {
        return ProjectForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProjectsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\MeasurementsRelationManager::class,
            RelationManagers\ProjectItemsRelationManager::class,
            RelationManagers\ProjectChaptersRelationManager::class,
            RelationManagers\WorkOrdersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjects::route('/'),
            'create' => CreateProject::route('/create'),
            'edit' => EditProject::route('/{record}/edit'),
            'costs' => ProjectCosts::route('/{record}/costs'),
        ];
    }

    public static function getWidgets(): array
    {
        return [ProjectCostStats::class, ProjectCostBreakdownChart::class, ProjectCostHistoryChart::class];
    }
}
