<?php

namespace App\Filament\Resources\WorkOrders\Schemas;

use App\Models\WorkOrder;
use App\Support\ProjectCosting;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class WorkOrderForm
{
    /** @param array<string, mixed> $context */
    public static function configure(Schema $schema, array $context = []): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('sales_invoices_count')->label(__('business.fields.invoicing'))->columnSpanFull()
                ->visible(fn (?WorkOrder $record): bool => (bool) $record?->exists)
                ->state(fn (WorkOrder $record): int => $record->salesInvoices()->count())
                ->formatStateUsing(fn (int $state): string => $state > 0
                    ? trans_choice('business.messages.linked_invoices', $state, ['count' => $state])
                    : __('business.messages.no_linked_invoices'))
                ->badge()->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray'),
            TextEntry::make('actual_cost_summary')->label(__('costing.costs'))->columnSpanFull()->visible(fn (?WorkOrder $record): bool => (bool) $record?->exists)
                ->state(function (WorkOrder $record): string {
                    return __('costing.cost_summary', app(ProjectCosting::class)->totals($record));
                }),
            (array_key_exists('project_id', $context) ? Hidden::make('project_id')->default($context['project_id']) : Select::make('project_id')->relationship('project', 'name')->searchable()->preload()->live()->afterStateUpdated(function (Set $set): void {
                $set('work_order_id', null);
                $set('project_chapter_id', null);
                $set('work_order_phase_id', null);
            }))->label(__('business.fields.project_id'))->required(),
            TextInput::make('reference')->label(__('business.fields.reference'))->required()->maxLength(80)->unique(ignoreRecord: true),
            TextInput::make('name')->label(__('business.fields.name'))->required()->maxLength(255),
            Textarea::make('description')->rows(3)->columnSpanFull()->label(__('business.fields.description')),
            (array_key_exists('supervisor_id', $context) ? Hidden::make('supervisor_id')->default($context['supervisor_id']) : Select::make('supervisor_id')->relationship('supervisor', 'name')->searchable()->preload())->label(__('business.fields.supervisor_id')),
            Select::make('status')->options(['draft' => __('business.options.draft'), 'planned' => __('business.options.planned'), 'in_progress' => __('business.options.in_progress'), 'on_hold' => __('business.options.on_hold'), 'completed' => __('business.options.completed'), 'cancelled' => __('business.options.cancelled')])->label(__('business.fields.status'))->required()->default('draft'),
            Select::make('priority')->options(['low' => __('business.options.low'), 'normal' => __('business.options.normal'), 'high' => __('business.options.high'), 'urgent' => __('business.options.urgent')])->label(__('business.fields.priority'))->required()->default('normal'),
            DatePicker::make('planned_start_date')->native(false)->displayFormat('d/m/Y')->label(__('business.fields.planned_start_date')),
            DatePicker::make('planned_end_date')->native(false)->displayFormat('d/m/Y')->label(__('business.fields.planned_end_date'))->afterOrEqual(fn (Get $get): ?string => $get('planned_start_date')),
            DatePicker::make('actual_start_date')->native(false)->displayFormat('d/m/Y')->label(__('business.fields.actual_start_date')),
            DatePicker::make('actual_end_date')->native(false)->displayFormat('d/m/Y')->label(__('business.fields.actual_end_date'))->afterOrEqual(fn (Get $get): ?string => $get('actual_start_date')),
            Textarea::make('instructions')->rows(3)->columnSpanFull()->label(__('business.fields.instructions')),
        ]);
    }
}
