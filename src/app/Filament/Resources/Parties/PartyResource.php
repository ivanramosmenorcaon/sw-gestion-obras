<?php

namespace App\Filament\Resources\Parties;

use App\Filament\Resources\BusinessResource;
use App\Filament\Resources\Parties\Pages\CreateParty;
use App\Filament\Resources\Parties\Pages\EditParty;
use App\Filament\Resources\Parties\Pages\ListParties;
use App\Filament\Resources\Parties\Schemas\PartyForm;
use App\Filament\Resources\Parties\Tables\PartiesTable;
use App\Models\Party;
use App\Support\BusinessRecordValidator;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PartyResource extends BusinessResource
{
    protected static ?int $navigationSort = 50;

    protected static ?string $model = Party::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::AddressBook;

    protected static ?string $recordTitleAttribute = 'legal_name';

    public static function getNavigationGroup(): ?string
    {
        return null;
    }

    public static function getModelLabel(): string
    {
        return __('business.models.parties.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('business.models.parties.plural');
    }

    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record) && BusinessRecordValidator::canDelete($record);
    }

    public static function form(Schema $schema): Schema
    {
        return PartyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PartiesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PartyRolesRelationManager::class,
            RelationManagers\PartyAddressesRelationManager::class,
            RelationManagers\PartyContactsRelationManager::class,
            RelationManagers\SupplierProductsRelationManager::class,
            RelationManagers\ProjectsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListParties::route('/'),
            'create' => CreateParty::route('/create'),
            'edit' => EditParty::route('/{record}/edit'),
        ];
    }
}
