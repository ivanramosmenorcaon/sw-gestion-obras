<?php

namespace App\Filament\Resources\UnitConversions;

use App\Filament\Clusters\Catalog\CatalogCluster;
use App\Filament\Resources\BusinessResource;
use App\Filament\Resources\UnitConversions\Pages\CreateUnitConversion;
use App\Filament\Resources\UnitConversions\Pages\EditUnitConversion;
use App\Filament\Resources\UnitConversions\Pages\ListUnitConversions;
use App\Filament\Resources\UnitConversions\Schemas\UnitConversionForm;
use App\Filament\Resources\UnitConversions\Tables\UnitConversionsTable;
use App\Models\UnitConversion;
use App\Support\BusinessRecordValidator;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UnitConversionResource extends BusinessResource
{
    protected static ?string $cluster = CatalogCluster::class;

    protected static ?int $navigationSort = 38;

    protected static ?string $model = UnitConversion::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::ArrowsExchange;

    protected static ?string $recordTitleAttribute = 'factor';

    protected static bool $shouldRegisterNavigation = false;

    public static function getModelLabel(): string
    {
        return __('business.models.unit_conversions.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('business.models.unit_conversions.plural');
    }

    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record) && BusinessRecordValidator::canDelete($record);
    }

    public static function form(Schema $schema): Schema
    {
        return UnitConversionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UnitConversionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [

        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUnitConversions::route('/'),
            'create' => CreateUnitConversion::route('/create'),
            'edit' => EditUnitConversion::route('/{record}/edit'),
        ];
    }
}
