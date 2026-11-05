<?php

namespace App\Filament\Resources\PartyAddresses;

use App\Filament\Resources\BusinessResource;
use App\Filament\Resources\PartyAddresses\Pages\CreatePartyAddress;
use App\Filament\Resources\PartyAddresses\Pages\EditPartyAddress;
use App\Filament\Resources\PartyAddresses\Pages\ListPartyAddresses;
use App\Filament\Resources\PartyAddresses\Schemas\PartyAddressForm;
use App\Filament\Resources\PartyAddresses\Tables\PartyAddressesTable;
use App\Models\PartyAddress;
use App\Support\BusinessRecordValidator;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PartyAddressResource extends BusinessResource
{
    protected static ?string $model = PartyAddress::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'label';

    protected static bool $shouldRegisterNavigation = false;

    public static function getModelLabel(): string
    {
        return __('business.models.party_addresses.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('business.models.party_addresses.plural');
    }

    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record) && BusinessRecordValidator::canDelete($record);
    }

    public static function form(Schema $schema): Schema
    {
        return PartyAddressForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PartyAddressesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [

        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPartyAddresses::route('/'),
            'create' => CreatePartyAddress::route('/create'),
            'edit' => EditPartyAddress::route('/{record}/edit'),
        ];
    }
}
