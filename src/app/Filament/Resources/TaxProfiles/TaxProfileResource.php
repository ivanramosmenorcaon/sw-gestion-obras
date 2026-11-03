<?php

namespace App\Filament\Resources\TaxProfiles;

use App\Filament\Resources\TaxProfiles\Pages\CreateTaxProfile;
use App\Filament\Resources\TaxProfiles\Pages\EditTaxProfile;
use App\Filament\Resources\TaxProfiles\Pages\ListTaxProfiles;
use App\Filament\Resources\TaxProfiles\Schemas\TaxProfileForm;
use App\Filament\Resources\TaxProfiles\Tables\TaxProfilesTable;
use App\Models\TaxProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class TaxProfileResource extends Resource
{
    protected static ?string $model = TaxProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): string
    {
        return __('fiscal.group');
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
