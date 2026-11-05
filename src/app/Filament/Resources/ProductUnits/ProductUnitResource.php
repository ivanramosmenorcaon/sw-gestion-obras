<?php

namespace App\Filament\Resources\ProductUnits;

use App\Filament\Resources\BusinessResource;
use App\Filament\Resources\ProductUnits\Pages\CreateProductUnit;
use App\Filament\Resources\ProductUnits\Pages\EditProductUnit;
use App\Filament\Resources\ProductUnits\Pages\ListProductUnits;
use App\Filament\Resources\ProductUnits\Schemas\ProductUnitForm;
use App\Filament\Resources\ProductUnits\Tables\ProductUnitsTable;
use App\Models\ProductUnit;
use App\Support\BusinessRecordValidator;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ProductUnitResource extends BusinessResource
{
    protected static ?string $model = ProductUnit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $shouldRegisterNavigation = false;

    public static function getModelLabel(): string
    {
        return __('business.models.product_units.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('business.models.product_units.plural');
    }

    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record) && BusinessRecordValidator::canDelete($record);
    }

    public static function form(Schema $schema): Schema
    {
        return ProductUnitForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductUnitsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [

        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductUnits::route('/'),
            'create' => CreateProductUnit::route('/create'),
            'edit' => EditProductUnit::route('/{record}/edit'),
        ];
    }
}
