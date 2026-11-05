<?php

namespace App\Filament\Resources\Users;

use App\Filament\Clusters\Settings\SettingsCluster;
use App\Filament\Resources\BusinessResource;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use App\Support\OperationalAccess;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserResource extends BusinessResource
{
    protected static ?string $cluster = SettingsCluster::class;

    protected static ?int $navigationSort = 45;

    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::ShieldLock;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nombre')->required()->maxLength(255),
            TextInput::make('email')->label('Correo')->email()->required()->unique(ignoreRecord: true),
            TextInput::make('password')->label('Contraseña')->password()->required(fn (string $operation): bool => $operation === 'create')->dehydrated(fn (?string $state): bool => filled($state))->minLength(12),
            Select::make('role')->label('Perfil')->options(OperationalAccess::ROLES)->required()->default('worker'),
            Toggle::make('is_active')->label('Acceso activo')->default(true),
        ]);
    }

    public static function getModelLabel(): string
    {
        return 'usuario';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Usuarios y permisos';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Acceso y control';
    }

    public static function table(Table $table): Table
    {
        return $table->dragReorderableColumns()->stickableColumns()->columns([
            TextColumn::make('name')->label('Nombre')->searchable(),
            TextColumn::make('email')->label('Correo')->searchable(),
            TextColumn::make('role')->label('Perfil')->formatStateUsing(fn (string $state): string => OperationalAccess::ROLES[$state] ?? $state),
            IconColumn::make('is_active')->label('Activo')->boolean(),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}
