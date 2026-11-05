<?php

namespace App\Filament\Resources\InvoiceMaturities;

use App\Filament\Resources\BusinessResource;
use App\Filament\Resources\InvoiceMaturities\Pages\ManageInvoiceMaturities;
use App\Filament\Schemas\OperationsForms;
use App\Models\InvoiceMaturity;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class InvoiceMaturityResource extends BusinessResource
{
    protected static ?string $model = InvoiceMaturity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function getModelLabel(): string
    {
        return OperationsForms::LABELS['InvoiceMaturity'];
    }

    public static function getPluralModelLabel(): string
    {
        return static::getModelLabel();
    }

    public static function getNavigationGroup(): string
    {
        return 'Obras';
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
            'index' => ManageInvoiceMaturities::route('/'),
        ];
    }
}
