<?php

namespace App\Filament\Resources\WorkOrderLines;

use App\Filament\Resources\WorkOrderLines\Pages\CreateWorkOrderLine;
use App\Filament\Resources\WorkOrderLines\Pages\EditWorkOrderLine;
use App\Filament\Resources\WorkOrderLines\Pages\ListWorkOrderLines;
use App\Filament\Resources\WorkOrderLines\Schemas\WorkOrderLineForm;
use App\Filament\Resources\WorkOrderLines\Tables\WorkOrderLinesTable;
use App\Models\WorkOrderLine;
use App\Support\BusinessRecordValidator;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class WorkOrderLineResource extends Resource
{
    protected static ?string $model = WorkOrderLine::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'description';

    protected static bool $shouldRegisterNavigation = false;

    public static function getModelLabel(): string
    {
        return __('business.models.work_order_lines.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('business.models.work_order_lines.plural');
    }

    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record) && BusinessRecordValidator::canDelete($record);
    }

    public static function form(Schema $schema): Schema
    {
        return WorkOrderLineForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkOrderLinesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [

        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkOrderLines::route('/'),
            'create' => CreateWorkOrderLine::route('/create'),
            'edit' => EditWorkOrderLine::route('/{record}/edit'),
        ];
    }
}
