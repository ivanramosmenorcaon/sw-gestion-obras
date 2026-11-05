<?php

namespace App\Filament\Resources\WorkOrderEmployeeAssignments;

use App\Filament\Clusters\Works\WorksCluster;
use App\Filament\Resources\BusinessResource;
use App\Filament\Resources\WorkOrderEmployeeAssignments\Pages\CreateWorkOrderEmployeeAssignment;
use App\Filament\Resources\WorkOrderEmployeeAssignments\Pages\EditWorkOrderEmployeeAssignment;
use App\Filament\Resources\WorkOrderEmployeeAssignments\Pages\ListWorkOrderEmployeeAssignments;
use App\Filament\Resources\WorkOrderEmployeeAssignments\Schemas\WorkOrderEmployeeAssignmentForm;
use App\Filament\Resources\WorkOrderEmployeeAssignments\Tables\WorkOrderEmployeeAssignmentsTable;
use App\Models\WorkOrderEmployeeAssignment;
use App\Support\BusinessRecordValidator;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class WorkOrderEmployeeAssignmentResource extends BusinessResource
{
    protected static ?string $cluster = WorksCluster::class;

    protected static ?int $navigationSort = 8;

    protected static ?string $model = WorkOrderEmployeeAssignment::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::UserCheck;

    protected static ?string $recordTitleAttribute = 'role';

    protected static bool $shouldRegisterNavigation = false;

    public static function getModelLabel(): string
    {
        return __('business.models.work_order_employee_assignments.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('business.models.work_order_employee_assignments.plural');
    }

    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record) && BusinessRecordValidator::canDelete($record);
    }

    public static function form(Schema $schema): Schema
    {
        return WorkOrderEmployeeAssignmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkOrderEmployeeAssignmentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [

        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkOrderEmployeeAssignments::route('/'),
            'create' => CreateWorkOrderEmployeeAssignment::route('/create'),
            'edit' => EditWorkOrderEmployeeAssignment::route('/{record}/edit'),
        ];
    }
}
