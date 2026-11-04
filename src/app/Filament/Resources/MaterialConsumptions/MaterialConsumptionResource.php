<?php

namespace App\Filament\Resources\MaterialConsumptions;

use App\Filament\Resources\MaterialConsumptions\Pages\CreateMaterialConsumption;
use App\Filament\Resources\MaterialConsumptions\Pages\EditMaterialConsumption;
use App\Filament\Resources\MaterialConsumptions\Pages\ListMaterialConsumptions;
use App\Filament\Resources\MaterialConsumptions\Pages\ViewMaterialConsumption;
use App\Filament\Resources\MaterialConsumptions\Schemas\MaterialConsumptionForm;
use App\Filament\Resources\MaterialConsumptions\Schemas\MaterialConsumptionInfolist;
use App\Filament\Resources\MaterialConsumptions\Tables\MaterialConsumptionsTable;
use App\Filament\Resources\WorkOrderPhases\WorkOrderPhaseResource;
use App\Models\MaterialConsumption;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class MaterialConsumptionResource extends Resource
{
    protected static ?string $model = MaterialConsumption::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::BasketMinus;

    protected static ?string $recordTitleAttribute = 'id';

    public static function getModelLabel(): string
    {
        return __('costing.consumption');
    }

    public static function getPluralModelLabel(): string
    {
        return __('costing.consumptions');
    }

    public static function getNavigationGroup(): string
    {
        return __('costing.group');
    }

    public static function canEdit(Model $record): bool
    {
        return $record->status === 'draft' && WorkOrderPhaseResource::canEdit($record->workOrderPhase) && parent::canEdit($record);
    }

    public static function canView(Model $record): bool
    {
        return WorkOrderPhaseResource::canView($record->workOrderPhase) && parent::canView($record);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return MaterialConsumptionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MaterialConsumptionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MaterialConsumptionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaterialConsumptions::route('/'),
            'create' => CreateMaterialConsumption::route('/create'),
            'view' => ViewMaterialConsumption::route('/{record}'),
            'edit' => EditMaterialConsumption::route('/{record}/edit'),
        ];
    }
}
