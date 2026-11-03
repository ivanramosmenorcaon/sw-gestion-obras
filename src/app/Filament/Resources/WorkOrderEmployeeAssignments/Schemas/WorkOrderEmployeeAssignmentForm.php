<?php

namespace App\Filament\Resources\WorkOrderEmployeeAssignments\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WorkOrderEmployeeAssignmentForm
{
    /** @param array<string, mixed> $context */
    public static function configure(Schema $schema, array $context = []): Schema
    {
        return $schema->columns(2)->components([
            (array_key_exists('work_order_id', $context) ? Hidden::make('work_order_id')->default($context['work_order_id']) : Select::make('work_order_id')->relationship('workOrder', 'name')->searchable()->preload()->live()->afterStateUpdated(function (Set $set): void {
                $set('work_order_phase_id', null);
            }))->label(__('business.fields.work_order_id'))->required(),
            (array_key_exists('work_order_phase_id', $context) ? Hidden::make('work_order_phase_id')->default($context['work_order_phase_id']) : Select::make('work_order_phase_id')->relationship('workOrderPhase', 'name', modifyQueryUsing: function (Builder $query, Get $get, ?Model $record): Builder {
                $query->where('work_order_id', $get('work_order_id'));

                return $query;
            })->searchable()->preload())->label(__('business.fields.work_order_phase_id')),
            (array_key_exists('employee_id', $context) ? Hidden::make('employee_id')->default($context['employee_id']) : Select::make('employee_id')->relationship('employee', 'name')->searchable()->preload())->label(__('business.fields.employee_id'))->required(),
            TextInput::make('role')->label(__('business.fields.role'))->maxLength(120),
            Select::make('status')->options(['planned' => __('business.options.planned'), 'active' => __('business.options.active'), 'completed' => __('business.options.completed'), 'cancelled' => __('business.options.cancelled')])->label(__('business.fields.status'))->required()->default('planned'),
            DatePicker::make('planned_start_date')->native(false)->displayFormat('d/m/Y')->label(__('business.fields.planned_start_date')),
            DatePicker::make('planned_end_date')->native(false)->displayFormat('d/m/Y')->label(__('business.fields.planned_end_date'))->afterOrEqual(fn (Get $get): ?string => $get('planned_start_date')),
            TextInput::make('planned_hours')->label(__('business.fields.planned_hours'))->required()->default('0')->numeric()->minValue(0),
            Textarea::make('notes')->rows(3)->columnSpanFull()->label(__('business.fields.notes')),
        ]);
    }
}
