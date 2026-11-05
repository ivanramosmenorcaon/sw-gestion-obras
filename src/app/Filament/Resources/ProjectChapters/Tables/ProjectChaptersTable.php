<?php

namespace App\Filament\Resources\ProjectChapters\Tables;

use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProjectChaptersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->dragReorderableColumns()
            ->stickableColumns()
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withExists(['children', 'workOrderLines']))->columns([
                TextColumn::make('project.name')->searchable()->sortable()->label(__('business.fields.project_id'))->toggleable(),
                TextColumn::make('parent.name')->searchable()->sortable()->label(__('business.fields.parent_id'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('code')->searchable()->sortable()->label(__('business.fields.code'))->toggleable(),
                TextColumn::make('name')->searchable()->sortable()->label(__('business.fields.name'))->toggleable(),
                TextColumn::make('position')->searchable()->sortable()->label(__('business.fields.position'))->toggleable(),
                IconColumn::make('is_active')->boolean()->label(__('business.fields.is_active'))->toggleable(),
                TextColumn::make('updated_at')->label(__('business.fields.updated_at'))->dateTime('d/m/Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])->filters([
                TernaryFilter::make('is_active')->label(__('business.fields.is_active')),
            ])->recordActions([EditAction::make()])->defaultSort('id', 'desc')
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }
}
