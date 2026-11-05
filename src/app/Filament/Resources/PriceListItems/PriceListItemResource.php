<?php

namespace App\Filament\Resources\PriceListItems;

use App\Filament\Clusters\Catalog\CatalogCluster;
use App\Filament\Resources\BusinessResource;
use App\Filament\Resources\PriceListItems\Pages\CreatePriceListItem;
use App\Filament\Resources\PriceListItems\Pages\EditPriceListItem;
use App\Filament\Resources\PriceListItems\Pages\ListPriceListItems;
use App\Filament\Resources\PriceListItems\Schemas\PriceListItemForm;
use App\Filament\Resources\PriceListItems\Tables\PriceListItemsTable;
use App\Models\PriceListItem;
use App\Support\BusinessRecordValidator;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PriceListItemResource extends BusinessResource
{
    protected static ?string $cluster = CatalogCluster::class;

    protected static ?int $navigationSort = 36;

    protected static ?string $model = PriceListItem::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Tag;

    protected static ?string $recordTitleAttribute = 'unit_price';

    protected static bool $shouldRegisterNavigation = false;

    public static function getModelLabel(): string
    {
        return __('business.models.price_list_items.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('business.models.price_list_items.plural');
    }

    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record) && BusinessRecordValidator::canDelete($record);
    }

    public static function form(Schema $schema): Schema
    {
        return PriceListItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PriceListItemsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [

        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPriceListItems::route('/'),
            'create' => CreatePriceListItem::route('/create'),
            'edit' => EditPriceListItem::route('/{record}/edit'),
        ];
    }
}
