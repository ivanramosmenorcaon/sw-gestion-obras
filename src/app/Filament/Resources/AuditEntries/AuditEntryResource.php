<?php

namespace App\Filament\Resources\AuditEntries;

use App\Filament\Resources\AuditEntries\Pages\ManageAuditEntries;
use App\Filament\Resources\BusinessResource;
use App\Models\AuditEntry;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AuditEntryResource extends BusinessResource
{
    protected static ?string $model = AuditEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'event';

    public static function getModelLabel(): string
    {
        return 'evento de auditoría';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Auditoría';
    }

    public static function getNavigationGroup(): string
    {
        return 'Configuración';
    }

    public static function table(Table $table): Table
    {
        return $table->dragReorderableColumns()->stickableColumns()->defaultSort('id', 'desc')->columns([
            TextColumn::make('created_at')->label('Fecha')->dateTime('d/m/Y H:i:s'),
            TextColumn::make('user_id')->label('Usuario'),
            TextColumn::make('event')->label('Operación'),
            TextColumn::make('subject_type')->label('Tipo')->searchable(),
            TextColumn::make('subject_id')->label('Registro'),
            TextColumn::make('changed_fields')->label('Campos cambiados')->listWithLineBreaks(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAuditEntries::route('/'),
        ];
    }
}
