<?php

namespace App\Filament\Resources\PurchaseDeliveryNotes;

use App\Filament\Resources\BusinessResource;
use App\Filament\Resources\PurchaseDeliveryNotes\Pages\CreatePurchaseDeliveryNote;
use App\Filament\Resources\PurchaseDeliveryNotes\Pages\EditPurchaseDeliveryNote;
use App\Filament\Resources\PurchaseDeliveryNotes\Pages\ListPurchaseDeliveryNotes;
use App\Filament\Resources\PurchaseDeliveryNotes\Pages\ViewPurchaseDeliveryNote;
use App\Filament\Resources\PurchaseDeliveryNotes\RelationManagers\LinesRelationManager;
use App\Filament\Resources\PurchaseDeliveryNotes\Schemas\PurchaseDeliveryNoteForm;
use App\Filament\Resources\PurchaseDeliveryNotes\Schemas\PurchaseDeliveryNoteInfolist;
use App\Filament\Resources\PurchaseDeliveryNotes\Tables\PurchaseDeliveryNotesTable;
use App\Models\PurchaseDeliveryNote;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PurchaseDeliveryNoteResource extends BusinessResource
{
    protected static ?string $model = PurchaseDeliveryNote::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::FileInvoiceFilled;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function getModelLabel(): string
    {
        return __('costing.note');
    }

    public static function getPluralModelLabel(): string
    {
        return __('costing.notes');
    }

    public static function getNavigationGroup(): string
    {
        return __('costing.group');
    }

    public static function canEdit(Model $record): bool
    {
        return $record->status === 'draft' && parent::canEdit($record);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return PurchaseDeliveryNoteForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PurchaseDeliveryNoteInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchaseDeliveryNotesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            LinesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseDeliveryNotes::route('/'),
            'create' => CreatePurchaseDeliveryNote::route('/create'),
            'view' => ViewPurchaseDeliveryNote::route('/{record}'),
            'edit' => EditPurchaseDeliveryNote::route('/{record}/edit'),
        ];
    }
}
