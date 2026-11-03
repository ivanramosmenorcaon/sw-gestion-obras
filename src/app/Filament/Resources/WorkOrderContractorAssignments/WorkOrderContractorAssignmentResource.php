<?php

namespace App\Filament\Resources\WorkOrderContractorAssignments;

use App\Filament\Resources\WorkOrderContractorAssignments\Pages\CreateWorkOrderContractorAssignment;
use App\Filament\Resources\WorkOrderContractorAssignments\Pages\EditWorkOrderContractorAssignment;
use App\Filament\Resources\WorkOrderContractorAssignments\Pages\ListWorkOrderContractorAssignments;
use App\Filament\Resources\WorkOrderContractorAssignments\Schemas\WorkOrderContractorAssignmentForm;
use App\Filament\Resources\WorkOrderContractorAssignments\Tables\WorkOrderContractorAssignmentsTable;
use App\Models\WorkOrderContractorAssignment;
use App\Support\BusinessRecordValidator;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class WorkOrderContractorAssignmentResource extends Resource
{
    protected static ?string $model = WorkOrderContractorAssignment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'scope';

    protected static bool $shouldRegisterNavigation = false;

    public static function getModelLabel(): string
    {
        return __('business.models.work_order_contractor_assignments.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('business.models.work_order_contractor_assignments.plural');
    }

    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record) && BusinessRecordValidator::canDelete($record);
    }

    public static function form(Schema $schema): Schema
    {
        return WorkOrderContractorAssignmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkOrderContractorAssignmentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [

        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkOrderContractorAssignments::route('/'),
            'create' => CreateWorkOrderContractorAssignment::route('/create'),
            'edit' => EditWorkOrderContractorAssignment::route('/{record}/edit'),
        ];
    }
}
