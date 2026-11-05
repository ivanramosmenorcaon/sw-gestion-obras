<?php

namespace App\Filament\Resources\EmployeeAbsences;

use App\Filament\Clusters\Team\TeamCluster;
use App\Filament\Resources\BusinessResource;
use App\Filament\Resources\EmployeeAbsences\Pages\ManageEmployeeAbsences;
use App\Filament\Schemas\OperationsForms;
use App\Models\EmployeeAbsence;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EmployeeAbsenceResource extends BusinessResource
{
    protected static ?string $cluster = TeamCluster::class;

    protected static ?int $navigationSort = 22;

    protected static ?string $model = EmployeeAbsence::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::CalendarOff;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function getModelLabel(): string
    {
        return OperationsForms::LABELS['EmployeeAbsence'];
    }

    public static function getPluralModelLabel(): string
    {
        return static::getModelLabel();
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Personal';
    }

    public static function canEdit(Model $record): bool
    {
        return in_array($record->status, ['draft', 'posted'], true) && parent::canEdit($record);
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
        return OperationsForms::table($table, static::getModel())
            ->modifyQueryUsing(fn (Builder $query) => $query->with('employee'))
            ->columns([
                TextColumn::make('employee.name')->label('Empleado')->searchable()->sortable(),
                TextColumn::make('reference')->label('Referencia')->searchable()->sortable(),
                TextColumn::make('description')->label('Descripción / motivo')->wrap()->searchable(),
                TextColumn::make('starts_on')->label('Desde')->date('d/m/Y')->sortable(),
                TextColumn::make('ends_on')->label('Hasta')->date('d/m/Y')->sortable(),
                TextColumn::make('status')->label('Estado')->badge()
                    ->formatStateUsing(fn (string $state): string => ['draft' => 'Pendiente', 'posted' => 'Confirmada', 'cancelled' => 'Cancelada'][$state] ?? $state),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEmployeeAbsences::route('/'),
        ];
    }
}
