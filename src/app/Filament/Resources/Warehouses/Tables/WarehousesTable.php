<?php

namespace App\Filament\Resources\Warehouses\Tables;

use App\Filament\Support\ActionIconsHelper;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WarehousesTable
{
    public static function configure(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->columns([TextColumn::make('code')->label(__('costing.fields.code'))->searchable(), TextColumn::make('name')->label(__('costing.fields.name'))->searchable(), TextColumn::make('is_active')->label(__('costing.fields.is_active'))])->recordActions(ActionIconsHelper::configureActions([ViewAction::make(), EditAction::make()]))->dragReorderableColumns()->stickableColumns();
    }
}
