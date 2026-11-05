<?php

namespace App\Filament\Resources\PurchaseInvoices;

use App\Filament\Clusters\Purchases\PurchasesCluster;
use App\Filament\Resources\BusinessResource;
use App\Filament\Resources\PurchaseInvoices\Pages\ManagePurchaseInvoices;
use App\Filament\Schemas\OperationsForms;
use App\Models\PurchaseInvoice;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PurchaseInvoiceResource extends BusinessResource
{
    protected static ?string $cluster = PurchasesCluster::class;

    protected static ?int $navigationSort = 16;

    protected static ?string $model = PurchaseInvoice::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::FileInvoice;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function getModelLabel(): string
    {
        return OperationsForms::LABELS['PurchaseInvoice'];
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
            'index' => ManagePurchaseInvoices::route('/'),
        ];
    }
}
