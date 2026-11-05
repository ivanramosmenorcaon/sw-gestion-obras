<?php

namespace App\Filament\Resources\Products;

use App\Filament\Clusters\Catalog\CatalogCluster;
use App\Filament\Resources\BusinessResource;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\Schemas\ProductForm;
use App\Filament\Resources\Products\Tables\ProductsTable;
use App\Models\Product;
use App\Support\BusinessRecordValidator;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ProductResource extends BusinessResource
{
    protected static ?string $cluster = CatalogCluster::class;

    protected static ?int $navigationSort = 31;

    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Cube;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return 'Productos y tarifas';
    }

    public static function getModelLabel(): string
    {
        return __('business.models.products.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('business.models.products.plural');
    }

    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record) && BusinessRecordValidator::canDelete($record);
    }

    public static function form(Schema $schema): Schema
    {
        return ProductForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ProductUnitsRelationManager::class,
            RelationManagers\SupplierProductsRelationManager::class,
            RelationManagers\PriceListItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
