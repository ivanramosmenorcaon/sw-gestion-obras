<?php

namespace App\Filament\Resources\TaxRates;

use App\Filament\Clusters\Settings\SettingsCluster;
use App\Filament\Resources\BusinessResource;
use App\Filament\Resources\TaxRates\Pages\CreateTaxRate;
use App\Filament\Resources\TaxRates\Pages\EditTaxRate;
use App\Filament\Resources\TaxRates\Pages\ListTaxRates;
use App\Filament\Resources\TaxRates\Schemas\TaxRateForm;
use App\Filament\Resources\TaxRates\Tables\TaxRatesTable;
use App\Models\TaxRate;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class TaxRateResource extends BusinessResource
{
    protected static ?string $cluster = SettingsCluster::class;

    protected static ?int $navigationSort = 43;

    protected static ?string $model = TaxRate::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Percentage;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return 'Fiscalidad';
    }

    public static function getModelLabel(): string
    {
        return __('fiscal.rate');
    }

    public static function getPluralModelLabel(): string
    {
        return __('fiscal.rates');
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return TaxRateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TaxRatesTable::configure($table);
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
            'index' => ListTaxRates::route('/'),
            'create' => CreateTaxRate::route('/create'),
            'edit' => EditTaxRate::route('/{record}/edit'),
        ];
    }
}
