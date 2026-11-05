<?php

namespace App\Filament\Resources\Employees;

use App\Filament\Clusters\Team\TeamCluster;
use App\Filament\Resources\BusinessResource;
use App\Filament\Resources\Employees\Pages\CreateEmployee;
use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Filament\Resources\Employees\Schemas\EmployeeForm;
use App\Filament\Resources\Employees\Tables\EmployeesTable;
use App\Models\Employee;
use App\Support\BusinessRecordValidator;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class EmployeeResource extends BusinessResource
{
    protected static ?string $cluster = TeamCluster::class;

    protected static ?int $navigationSort = 21;

    protected static ?string $model = Employee::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::User;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return 'Personal';
    }

    public static function getModelLabel(): string
    {
        return __('business.models.employees.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('business.models.employees.plural');
    }

    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record) && BusinessRecordValidator::canDelete($record);
    }

    public static function form(Schema $schema): Schema
    {
        return EmployeeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\WorkOrderEmployeeAssignmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployees::route('/'),
            'create' => CreateEmployee::route('/create'),
            'edit' => EditEmployee::route('/{record}/edit'),
        ];
    }
}
