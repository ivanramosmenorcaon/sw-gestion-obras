<?php

namespace App\Filament\Resources\InvoiceSeries;

use App\Filament\Resources\InvoiceSeries\Pages\CreateInvoiceSeries;
use App\Filament\Resources\InvoiceSeries\Pages\EditInvoiceSeries;
use App\Filament\Resources\InvoiceSeries\Pages\ListInvoiceSeries;
use App\Filament\Resources\InvoiceSeries\Schemas\InvoiceSeriesForm;
use App\Filament\Resources\InvoiceSeries\Tables\InvoiceSeriesTable;
use App\Models\InvoiceSeries;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class InvoiceSeriesResource extends Resource
{
    protected static ?string $model = InvoiceSeries::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::NumberedList;

    protected static ?string $recordTitleAttribute = 'code';

    public static function getNavigationGroup(): string
    {
        return __('billing.group');
    }

    public static function getModelLabel(): string
    {
        return __('billing.series_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('billing.series');
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return InvoiceSeriesForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InvoiceSeriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoiceSeries::route('/'),
            'create' => CreateInvoiceSeries::route('/create'),
            'edit' => EditInvoiceSeries::route('/{record}/edit'),
        ];
    }
}
