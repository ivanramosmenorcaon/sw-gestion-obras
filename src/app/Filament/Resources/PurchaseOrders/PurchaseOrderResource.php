<?php

namespace App\Filament\Resources\PurchaseOrders;

use App\Filament\Clusters\Purchases\PurchasesCluster;
use App\Filament\Resources\BusinessResource;
use App\Filament\Resources\PurchaseOrders\Pages\ManagePurchaseOrders;
use App\Filament\Schemas\OperationsForms;
use App\Models\PurchaseOrder;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderResource extends BusinessResource
{
    protected static ?string $cluster = PurchasesCluster::class;

    protected static ?int $navigationSort = 14;

    protected static ?string $model = PurchaseOrder::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::ShoppingCart;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function getModelLabel(): string
    {
        return OperationsForms::LABELS['PurchaseOrder'];
    }

    public static function getPluralModelLabel(): string
    {
        return static::getModelLabel();
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Compras';
    }

    public static function canEdit(Model $record): bool
    {
        return $record->status === 'draft' && parent::canEdit($record);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return OperationsForms::form($schema, static::getModel());
    }

    public static function table(Table $table): Table
    {
        return OperationsForms::table($table, static::getModel());
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePurchaseOrders::route('/'),
        ];
    }
}
