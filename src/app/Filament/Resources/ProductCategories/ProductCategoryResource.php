<?php

namespace App\Filament\Resources\ProductCategories;

use App\Filament\Clusters\Catalog\CatalogCluster;
use App\Filament\Resources\BusinessResource;
use App\Filament\Resources\ProductCategories\Pages\CreateProductCategory;
use App\Filament\Resources\ProductCategories\Pages\EditProductCategory;
use App\Filament\Resources\ProductCategories\Pages\ListProductCategories;
use App\Filament\Resources\ProductCategories\Schemas\ProductCategoryForm;
use App\Filament\Resources\ProductCategories\Tables\ProductCategoriesTable;
use App\Models\ProductCategory;
use App\Support\BusinessRecordValidator;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ProductCategoryResource extends BusinessResource
{
    protected static ?string $cluster = CatalogCluster::class;

    protected static ?int $navigationSort = 32;

    protected static ?string $model = ProductCategory::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Category;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return 'Productos y tarifas';
    }

    public static function getModelLabel(): string
    {
        return __('business.models.product_categories.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('business.models.product_categories.plural');
    }

    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record) && BusinessRecordValidator::canDelete($record);
    }

    public static function form(Schema $schema): Schema
    {
        return ProductCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductCategoriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [

        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductCategories::route('/'),
            'create' => CreateProductCategory::route('/create'),
            'edit' => EditProductCategory::route('/{record}/edit'),
        ];
    }
}
