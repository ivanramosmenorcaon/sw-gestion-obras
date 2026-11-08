<?php

namespace App\Filament\Resources\Projects\Tables;

use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Support\ActionIconsHelper;
use App\Models\Project;
use App\Support\ProjectCostReport;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->dragReorderableColumns()
            ->stickableColumns()
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withExists(['projectChapters', 'workOrders', 'workOrderLines']))->columns([
                TextColumn::make('reference')->searchable()->sortable()->label(__('business.fields.reference'))->toggleable(),
                TextColumn::make('name')->searchable()->sortable()->label(__('business.fields.name'))->toggleable(),
                TextColumn::make('customer.legal_name')->searchable()->sortable()->label(__('business.fields.customer_id'))->toggleable(),
                TextColumn::make('manager.name')->searchable()->sortable()->label(__('business.fields.manager_id'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')->badge()->formatStateUsing(fn (?string $state): string => $state === null ? '' : __('business.options.'.$state))->label(__('business.fields.status'))->toggleable(),
                TextColumn::make('currency_code')->searchable()->sortable()->label(__('business.fields.currency_code'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('address_line_1')->searchable()->sortable()->label(__('business.fields.address_line_1'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('address_line_2')->searchable()->sortable()->label(__('business.fields.address_line_2'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('city')->searchable()->sortable()->label(__('business.fields.city'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('province')->searchable()->sortable()->label(__('business.fields.province'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('postal_code')->searchable()->sortable()->label(__('business.fields.postal_code'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('country_code')->searchable()->sortable()->label(__('business.fields.country_code'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('planned_start_date')->date('d/m/Y')->sortable()->label(__('business.fields.planned_start_date'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('planned_end_date')->date('d/m/Y')->sortable()->label(__('business.fields.planned_end_date'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('actual_start_date')->date('d/m/Y')->sortable()->label(__('business.fields.actual_start_date'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('actual_end_date')->date('d/m/Y')->sortable()->label(__('business.fields.actual_end_date'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->label(__('business.fields.updated_at'))->dateTime('d/m/Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])->filters([
                SelectFilter::make('status')->label(__('business.fields.status'))->options(['draft' => __('business.options.draft'), 'quoted' => __('business.options.quoted'), 'approved' => __('business.options.approved'), 'in_progress' => __('business.options.in_progress'), 'on_hold' => __('business.options.on_hold'), 'completed' => __('business.options.completed'), 'cancelled' => __('business.options.cancelled')]),
            ])->recordActions(ActionIconsHelper::configureActions([
                Action::make('costs')->label('Costes y gráficas')
                    ->visible(fn (Project $record): bool => ProjectCostReport::canView($record))
                    ->url(fn (Project $record): string => ProjectResource::getUrl('costs', ['record' => $record])),
                EditAction::make(),
            ]))->defaultSort('id', 'desc');
    }
}
