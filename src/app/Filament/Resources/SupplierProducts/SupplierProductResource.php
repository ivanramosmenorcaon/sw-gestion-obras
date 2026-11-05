<?php

namespace App\Filament\Resources\SupplierProducts;

use App\Filament\Clusters\Catalog\CatalogCluster;
use App\Filament\Resources\BusinessResource;
use App\Filament\Resources\SupplierProducts\Pages\CreateSupplierProduct;
use App\Filament\Resources\SupplierProducts\Pages\EditSupplierProduct;
use App\Filament\Resources\SupplierProducts\Pages\ListSupplierProducts;
use App\Filament\Resources\SupplierProducts\Schemas\SupplierProductForm;
use App\Filament\Resources\SupplierProducts\Tables\SupplierProductsTable;
use App\Models\SupplierProduct;
use App\Support\BusinessRecordValidator;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SupplierProductResource extends BusinessResource
{
    protected static ?string $cluster = CatalogCluster::class;

    protected static ?int $navigationSort = 34;

    protected static ?string $model = SupplierProduct::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::PackageImport;

    protected static ?string $recordTitleAttribute = 'supplier_reference';

    protected static bool $shouldRegisterNavigation = false;

    public static function getModelLabel(): string
    {
        return __('business.models.supplier_products.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('business.models.supplier_products.plural');
    }

    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record) && BusinessRecordValidator::canDelete($record);
    }

    public static function form(Schema $schema): Schema
    {
        return SupplierProductForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SupplierProductsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [

        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupplierProducts::route('/'),
            'create' => CreateSupplierProduct::route('/create'),
            'edit' => EditSupplierProduct::route('/{record}/edit'),
        ];
    }
}
