<?php

namespace App\Filament\Resources\Units\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UnitsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->dragReorderableColumns()
            ->stickableColumns()
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withExists(['workOrderLines', 'workOrderLinesByBaseUnit', 'unitConversionsByFromUnit', 'unitConversionsByToUnit', 'productsByBaseUnit', 'productUnits']))->columns([
                TextColumn::make('code')->searchable()->sortable()->label(__('business.fields.code'))->toggleable(),
                TextColumn::make('name')->searchable()->sortable()->label(__('business.fields.name'))->toggleable(),
                TextColumn::make('symbol')->searchable()->sortable()->label(__('business.fields.symbol'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('magnitude')->badge()->formatStateUsing(fn (?string $state): string => $state === null ? '' : __('business.options.'.$state))->label(__('business.fields.magnitude'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('display_precision')->searchable()->sortable()->label(__('business.fields.display_precision'))->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')->boolean()->label(__('business.fields.is_active'))->toggleable(),
                TextColumn::make('updated_at')->label(__('business.fields.updated_at'))->dateTime('d/m/Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])->filters([
                SelectFilter::make('magnitude')->label(__('business.fields.magnitude'))->options(['count' => __('business.options.count'), 'time' => __('business.options.time'), 'mass' => __('business.options.mass'), 'length' => __('business.options.length'), 'area' => __('business.options.area'), 'volume' => __('business.options.volume')]),
                TernaryFilter::make('is_active')->label(__('business.fields.is_active')),
            ])->recordActions([EditAction::make()])->defaultSort('id', 'desc');
    }
}
