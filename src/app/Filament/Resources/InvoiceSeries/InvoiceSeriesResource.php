<?php

namespace App\Filament\Resources\InvoiceSeries;

use App\Filament\Clusters\Settings\SettingsCluster;
use App\Filament\Resources\BusinessResource;
use App\Filament\Resources\InvoiceSeries\Pages\CreateInvoiceSeries;
use App\Filament\Resources\InvoiceSeries\Pages\EditInvoiceSeries;
use App\Filament\Resources\InvoiceSeries\Pages\ListInvoiceSeries;
use App\Filament\Resources\InvoiceSeries\Schemas\InvoiceSeriesForm;
use App\Filament\Resources\InvoiceSeries\Tables\InvoiceSeriesTable;
use App\Models\InvoiceSeries;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class InvoiceSeriesResource extends BusinessResource
{
    protected static ?string $cluster = SettingsCluster::class;

    protected static ?int $navigationSort = 44;

    protected static ?string $model = InvoiceSeries::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::ListDetails;

    protected static ?string $recordTitleAttribute = 'code';

    public static function getNavigationGroup(): ?string
    {
        return 'Fiscalidad';
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
