<?php

namespace App\Filament\Resources\WorkOrders;

use App\Filament\Clusters\Works\WorksCluster;
use App\Filament\Resources\BusinessResource;
use App\Filament\Resources\WorkOrders\Pages\CreateWorkOrder;
use App\Filament\Resources\WorkOrders\Pages\EditWorkOrder;
use App\Filament\Resources\WorkOrders\Pages\ListWorkOrders;
use App\Filament\Resources\WorkOrders\Pages\ViewWorkOrderTree;
use App\Filament\Resources\WorkOrders\Schemas\WorkOrderForm;
use App\Filament\Resources\WorkOrders\Tables\WorkOrdersTable;
use App\Models\WorkOrder;
use App\Support\BusinessRecordValidator;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class WorkOrderResource extends BusinessResource
{
    protected static ?string $cluster = WorksCluster::class;

    protected static ?int $navigationSort = 4;

    protected static ?string $model = WorkOrder::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::ClipboardList;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return 'Ejecución';
    }

    public static function getModelLabel(): string
    {
        return __('business.models.work_orders.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('business.models.work_orders.plural');
    }

    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record) && BusinessRecordValidator::canDelete($record);
    }

    public static function form(Schema $schema): Schema
    {
        return WorkOrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkOrdersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\WorkOrderLinesRelationManager::class,
            RelationManagers\WorkOrderEmployeeAssignmentsRelationManager::class,
            RelationManagers\WorkOrderContractorAssignmentsRelationManager::class,
            RelationManagers\WorkOrderPhasesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkOrders::route('/'),
            'create' => CreateWorkOrder::route('/create'),
            'tree' => ViewWorkOrderTree::route('/{record}/arbol'),
            'edit' => EditWorkOrder::route('/{record}/edit'),
        ];
    }
}
