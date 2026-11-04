<?php

namespace App\Filament\Resources\LaborCostRates;

use App\Filament\Resources\LaborCostRates\Pages\CreateLaborCostRate;
use App\Filament\Resources\LaborCostRates\Pages\EditLaborCostRate;
use App\Filament\Resources\LaborCostRates\Pages\ListLaborCostRates;
use App\Filament\Resources\LaborCostRates\Pages\ViewLaborCostRate;
use App\Filament\Resources\LaborCostRates\Schemas\LaborCostRateForm;
use App\Filament\Resources\LaborCostRates\Schemas\LaborCostRateInfolist;
use App\Filament\Resources\LaborCostRates\Tables\LaborCostRatesTable;
use App\Models\LaborCostRate;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class LaborCostRateResource extends Resource
{
    protected static ?string $model = LaborCostRate::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::FileEuro;

    protected static ?string $recordTitleAttribute = 'id';

    public static function getModelLabel(): string
    {
        return __('costing.rate');
    }

    public static function getPluralModelLabel(): string
    {
        return __('costing.rates');
    }

    public static function getNavigationGroup(): string
    {
        return __('business.groups.team');
    }

    public static function canEdit(Model $record): bool
    {
        return parent::canEdit($record);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return LaborCostRateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return LaborCostRateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LaborCostRatesTable::configure($table);
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
            'index' => ListLaborCostRates::route('/'),
            'create' => CreateLaborCostRate::route('/create'),
            'view' => ViewLaborCostRate::route('/{record}'),
            'edit' => EditLaborCostRate::route('/{record}/edit'),
        ];
    }
}
