<?php

namespace App\Filament\Resources\PartyRoles;

use App\Filament\Resources\PartyRoles\Pages\CreatePartyRole;
use App\Filament\Resources\PartyRoles\Pages\EditPartyRole;
use App\Filament\Resources\PartyRoles\Pages\ListPartyRoles;
use App\Filament\Resources\PartyRoles\Schemas\PartyRoleForm;
use App\Filament\Resources\PartyRoles\Tables\PartyRolesTable;
use App\Models\PartyRole;
use App\Support\BusinessRecordValidator;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PartyRoleResource extends Resource
{
    protected static ?string $model = PartyRole::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'role';

    protected static bool $shouldRegisterNavigation = false;

    public static function getModelLabel(): string
    {
        return __('business.models.party_roles.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('business.models.party_roles.plural');
    }

    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record) && BusinessRecordValidator::canDelete($record);
    }

    public static function form(Schema $schema): Schema
    {
        return PartyRoleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PartyRolesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [

        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPartyRoles::route('/'),
            'create' => CreatePartyRole::route('/create'),
            'edit' => EditPartyRole::route('/{record}/edit'),
        ];
    }
}
