<?php

namespace App\Filament\Resources\WorkOrderPhases\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class WorkOrderPhaseForm
{
    /** @param array<string, mixed> $context */
    public static function configure(Schema $schema, array $context = []): Schema
    {
        return $schema->columns(2)->components([
            (array_key_exists('work_order_id', $context) ? Hidden::make('work_order_id')->default($context['work_order_id']) : Select::make('work_order_id')->relationship('workOrder', 'name')->searchable()->preload()->live()->afterStateUpdated(function (Set $set): void {
                $set('work_order_phase_id', null);
            }))->label(__('business.fields.work_order_id'))->required(),
            TextInput::make('code')->label(__('business.fields.code'))->required()->maxLength(50),
            TextInput::make('name')->label(__('business.fields.name'))->required()->maxLength(255),
            Textarea::make('description')->rows(3)->columnSpanFull()->label(__('business.fields.description')),
            TextInput::make('position')->label(__('business.fields.position'))->required()->default('1')->numeric()->minValue(1)->integer(),
            Select::make('status')->options(['pending' => __('business.options.pending'), 'in_progress' => __('business.options.in_progress'), 'on_hold' => __('business.options.on_hold'), 'completed' => __('business.options.completed'), 'cancelled' => __('business.options.cancelled')])->label(__('business.fields.status'))->required()->default('pending'),
            DatePicker::make('planned_start_date')->native(false)->displayFormat('d/m/Y')->label(__('business.fields.planned_start_date')),
            DatePicker::make('planned_end_date')->native(false)->displayFormat('d/m/Y')->label(__('business.fields.planned_end_date'))->afterOrEqual(fn (Get $get): ?string => $get('planned_start_date')),
            DatePicker::make('actual_start_date')->native(false)->displayFormat('d/m/Y')->label(__('business.fields.actual_start_date')),
            DatePicker::make('actual_end_date')->native(false)->displayFormat('d/m/Y')->label(__('business.fields.actual_end_date'))->afterOrEqual(fn (Get $get): ?string => $get('actual_start_date')),
        ]);
    }
}
