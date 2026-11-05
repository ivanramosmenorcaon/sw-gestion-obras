<?php

namespace App\Filament\Resources\TaxProfiles;

use App\Filament\Clusters\Settings\SettingsCluster;
use App\Filament\Resources\BusinessResource;
use App\Filament\Resources\TaxProfiles\Pages\CreateTaxProfile;
use App\Filament\Resources\TaxProfiles\Pages\EditTaxProfile;
use App\Filament\Resources\TaxProfiles\Pages\ListTaxProfiles;
use App\Filament\Resources\TaxProfiles\Schemas\TaxProfileForm;
use App\Filament\Resources\TaxProfiles\Tables\TaxProfilesTable;
use App\Models\TaxProfile;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class TaxProfileResource extends BusinessResource
{
    protected static ?string $cluster = SettingsCluster::class;

    protected static ?int $navigationSort = 42;

    protected static ?string $model = TaxProfile::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::ReceiptTax;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return 'Fiscalidad';
    }

    public static function getModelLabel(): string
    {
        return __('fiscal.profile');
    }

    public static function getPluralModelLabel(): string
    {
        return __('fiscal.profiles');
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return TaxProfileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TaxProfilesTable::configure($table);
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
            'index' => ListTaxProfiles::route('/'),
            'create' => CreateTaxProfile::route('/create'),
            'edit' => EditTaxProfile::route('/{record}/edit'),
        ];
    }
}
