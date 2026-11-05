<?php

namespace App\Filament\Resources\Budgets;

use App\Filament\Clusters\Works\WorksCluster;
use App\Filament\Resources\Budgets\Pages\EditBudget;
use App\Filament\Resources\Budgets\Pages\ListBudgets;
use App\Filament\Resources\Budgets\Schemas\BudgetForm;
use App\Filament\Resources\Budgets\Tables\BudgetsTable;
use App\Filament\Resources\BusinessResource;
use App\Models\Budget;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class BudgetResource extends BusinessResource
{
    protected static ?string $cluster = WorksCluster::class;

    protected static ?int $navigationSort = 2;

    protected static ?string $model = Budget::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::FileDescription;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return 'presupuesto';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Presupuestos';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Preparación';
    }

    public static function canDelete(Model $record): bool
    {
        return $record->status === 'draft' && ! Budget::where('revises_id', $record->id)->orWhere('extra_for_id', $record->id)->exists() && parent::canDelete($record);
    }

    public static function form(Schema $schema): Schema
    {
        return BudgetForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BudgetsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBudgets::route('/'),
            'edit' => EditBudget::route('/{record}/edit'),
        ];
    }
}
