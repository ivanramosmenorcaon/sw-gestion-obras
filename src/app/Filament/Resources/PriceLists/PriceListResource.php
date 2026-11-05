<?php

namespace App\Filament\Resources\PriceLists;

use App\Filament\Resources\BusinessResource;
use App\Filament\Resources\PriceLists\Pages\CreatePriceList;
use App\Filament\Resources\PriceLists\Pages\EditPriceList;
use App\Filament\Resources\PriceLists\Pages\ListPriceLists;
use App\Filament\Resources\PriceLists\Schemas\PriceListForm;
use App\Filament\Resources\PriceLists\Tables\PriceListsTable;
use App\Models\PriceList;
use App\Support\BusinessRecordValidator;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PriceListResource extends BusinessResource
{
    protected static ?string $model = PriceList::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): string
    {
        return __('business.groups.catalog');
    }

    public static function getModelLabel(): string
    {
        return __('business.models.price_lists.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('business.models.price_lists.plural');
    }

    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record) && BusinessRecordValidator::canDelete($record);
    }

    public static function form(Schema $schema): Schema
    {
        return PriceListForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PriceListsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PriceListItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPriceLists::route('/'),
            'create' => CreatePriceList::route('/create'),
            'edit' => EditPriceList::route('/{record}/edit'),
        ];
    }
}
