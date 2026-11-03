<?php

namespace App\Filament\Resources\PartyContacts;

use App\Filament\Resources\PartyContacts\Pages\CreatePartyContact;
use App\Filament\Resources\PartyContacts\Pages\EditPartyContact;
use App\Filament\Resources\PartyContacts\Pages\ListPartyContacts;
use App\Filament\Resources\PartyContacts\Schemas\PartyContactForm;
use App\Filament\Resources\PartyContacts\Tables\PartyContactsTable;
use App\Models\PartyContact;
use App\Support\BusinessRecordValidator;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PartyContactResource extends Resource
{
    protected static ?string $model = PartyContact::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $shouldRegisterNavigation = false;

    public static function getModelLabel(): string
    {
        return __('business.models.party_contacts.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('business.models.party_contacts.plural');
    }

    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record) && BusinessRecordValidator::canDelete($record);
    }

    public static function form(Schema $schema): Schema
    {
        return PartyContactForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PartyContactsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [

        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPartyContacts::route('/'),
            'create' => CreatePartyContact::route('/create'),
            'edit' => EditPartyContact::route('/{record}/edit'),
        ];
    }
}
