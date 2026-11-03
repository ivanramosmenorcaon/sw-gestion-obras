<?php

namespace App\Filament\Resources\WorkOrderLines\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WorkOrderLinesTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('description')->label(__('business.fields.description'))->limit(60)->searchable(),
            TextColumn::make('project.name')->searchable()->sortable()->label(__('business.fields.project_id'))->toggleable(),
            TextColumn::make('workOrder.name')->searchable()->sortable()->label(__('business.fields.work_order_id'))->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('projectChapter.name')->searchable()->sortable()->label(__('business.fields.project_chapter_id'))->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('workOrderPhase.name')->searchable()->sortable()->label(__('business.fields.work_order_phase_id'))->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('position')->searchable()->sortable()->label(__('business.fields.position'))->toggleable(),
            TextColumn::make('type')->badge()->formatStateUsing(fn (?string $state): string => $state === null ? '' : __('business.options.'.$state))->label(__('business.fields.type'))->toggleable(),
            TextColumn::make('product.name')->searchable()->sortable()->label(__('business.fields.product_id'))->toggleable(),
            TextColumn::make('productUnit.name')->searchable()->sortable()->label(__('business.fields.product_unit_id'))->toggleable(),
            TextColumn::make('unit.name')->searchable()->sortable()->label(__('business.fields.unit_id'))->toggleable(),
            TextColumn::make('unit_label')->searchable()->sortable()->label(__('business.fields.unit_label'))->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('baseUnit.name')->searchable()->sortable()->label(__('business.fields.base_unit_id'))->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('base_unit_factor')->numeric(decimalPlaces: 6, locale: 'es')->sortable()->label(__('business.fields.base_unit_factor'))->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('planned_quantity')->numeric(decimalPlaces: 6, locale: 'es')->sortable()->label(__('business.fields.planned_quantity'))->toggleable(),
            TextColumn::make('estimated_unit_cost')->numeric(decimalPlaces: 6, locale: 'es')->sortable()->label(__('business.fields.estimated_unit_cost'))->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('estimated_unit_sale_price')->numeric(decimalPlaces: 6, locale: 'es')->sortable()->label(__('business.fields.estimated_unit_sale_price'))->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')->label(__('business.fields.updated_at'))->dateTime('d/m/Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            SelectFilter::make('type')->label(__('business.fields.type'))->options(['material' => __('business.options.material'), 'service' => __('business.options.service'), 'labor' => __('business.options.labor'), 'rental' => __('business.options.rental')]),
        ])->recordActions([EditAction::make()])->defaultSort('id', 'desc');
    }
}
