<?php

namespace App\Filament\Resources\CompanySettings;

use App\Filament\Resources\BusinessResource;
use App\Filament\Resources\CompanySettings\Pages\CreateCompanySetting;
use App\Filament\Resources\CompanySettings\Pages\EditCompanySetting;
use App\Filament\Resources\CompanySettings\Pages\ListCompanySettings;
use App\Filament\Resources\CompanySettings\Schemas\CompanySettingForm;
use App\Filament\Resources\CompanySettings\Tables\CompanySettingsTable;
use App\Models\CompanySetting;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CompanySettingResource extends BusinessResource
{
    protected static ?string $model = CompanySetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $recordTitleAttribute = 'legal_name';

    public static function getNavigationGroup(): string
    {
        return __('business.groups.settings');
    }

    public static function getModelLabel(): string
    {
        return __('business.models.company_settings.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('business.models.company_settings.plural');
    }

    public static function canCreate(): bool
    {
        return parent::canCreate() && ! CompanySetting::query()->exists();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return CompanySettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CompanySettingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [

        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompanySettings::route('/'),
            'create' => CreateCompanySetting::route('/create'),
            'edit' => EditCompanySetting::route('/{record}/edit'),
        ];
    }
}
