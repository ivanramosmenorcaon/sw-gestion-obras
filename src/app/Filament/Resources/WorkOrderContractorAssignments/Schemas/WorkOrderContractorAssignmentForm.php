<?php

namespace App\Filament\Resources\WorkOrderContractorAssignments\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WorkOrderContractorAssignmentForm
{
    /** @param array<string, mixed> $context */
    public static function configure(Schema $schema, array $context = []): Schema
    {
        return $schema->columns(2)->components([
            Textarea::make('scope')->label(__('business.fields.scope'))->required()->columnSpanFull(),
            (array_key_exists('work_order_id', $context) ? Hidden::make('work_order_id')->default($context['work_order_id']) : Select::make('work_order_id')->relationship('workOrder', 'name')->searchable()->preload()->live()->afterStateUpdated(function (Set $set): void {
                $set('work_order_phase_id', null);
            }))->label(__('business.fields.work_order_id'))->required(),
            (array_key_exists('work_order_phase_id', $context) ? Hidden::make('work_order_phase_id')->default($context['work_order_phase_id']) : Select::make('work_order_phase_id')->relationship('workOrderPhase', 'name', modifyQueryUsing: function (Builder $query, Get $get, ?Model $record): Builder {
                $query->where('work_order_id', $get('work_order_id'));

                return $query;
            })->searchable()->preload())->label(__('business.fields.work_order_phase_id')),
            (array_key_exists('contractor_id', $context) ? Hidden::make('contractor_id')->default($context['contractor_id']) : Select::make('contractor_id')->relationship('contractor', 'legal_name', modifyQueryUsing: function (Builder $query, Get $get, ?Model $record): Builder {
                $query->whereHas('partyRoles', fn (Builder $roles): Builder => $roles->where('role', 'subcontractor'));

                return $query;
            })->searchable()->preload())->label(__('business.fields.contractor_id'))->required(),
            Select::make('status')->options(['planned' => __('business.options.planned'), 'active' => __('business.options.active'), 'completed' => __('business.options.completed'), 'cancelled' => __('business.options.cancelled')])->label(__('business.fields.status'))->required()->default('planned'),
            DatePicker::make('planned_start_date')->native(false)->displayFormat('d/m/Y')->label(__('business.fields.planned_start_date')),
            DatePicker::make('planned_end_date')->native(false)->displayFormat('d/m/Y')->label(__('business.fields.planned_end_date'))->afterOrEqual(fn (Get $get): ?string => $get('planned_start_date')),
            Textarea::make('notes')->rows(3)->columnSpanFull()->label(__('business.fields.notes')),
        ]);
    }
}
