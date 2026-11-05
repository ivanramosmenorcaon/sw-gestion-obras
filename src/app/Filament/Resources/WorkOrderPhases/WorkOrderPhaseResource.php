<?php

namespace App\Filament\Resources\WorkOrderPhases;

use App\Filament\Clusters\Works\WorksCluster;
use App\Filament\Resources\BusinessResource;
use App\Filament\Resources\WorkOrderPhases\Pages\CreateWorkOrderPhase;
use App\Filament\Resources\WorkOrderPhases\Pages\EditWorkOrderPhase;
use App\Filament\Resources\WorkOrderPhases\Pages\ListWorkOrderPhases;
use App\Filament\Resources\WorkOrderPhases\RelationManagers\MaterialConsumptionsRelationManager;
use App\Filament\Resources\WorkOrderPhases\RelationManagers\WorkTimeEntriesRelationManager;
use App\Filament\Resources\WorkOrderPhases\Schemas\WorkOrderPhaseForm;
use App\Filament\Resources\WorkOrderPhases\Tables\WorkOrderPhasesTable;
use App\Models\WorkOrderPhase;
use App\Support\BusinessRecordValidator;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class WorkOrderPhaseResource extends BusinessResource
{
    protected static ?string $cluster = WorksCluster::class;

    protected static ?int $navigationSort = 5;

    protected static ?string $model = WorkOrderPhase::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Timeline;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $shouldRegisterNavigation = false;

    public static function getModelLabel(): string
    {
        return __('business.models.work_order_phases.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('business.models.work_order_phases.plural');
    }

    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record) && BusinessRecordValidator::canDelete($record);
    }

    public static function form(Schema $schema): Schema
    {
        return WorkOrderPhaseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkOrderPhasesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [MaterialConsumptionsRelationManager::class, WorkTimeEntriesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkOrderPhases::route('/'),
            'create' => CreateWorkOrderPhase::route('/create'),
            'edit' => EditWorkOrderPhase::route('/{record}/edit'),
        ];
    }
}
