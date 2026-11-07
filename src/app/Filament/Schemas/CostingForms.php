<?php

namespace App\Filament\Schemas;

use App\Models\Product;
use App\Models\PurchaseDeliveryLine;
use App\Models\WorkOrderPhase;
use Brick\Math\BigDecimal;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class CostingForms
{
    /** @param array<string, int> $context */
    public static function configure(Schema $schema, string $type, array $context = []): Schema
    {
        $label = fn (string $key): string => __('costing.fields.'.$key);
        $number = fn (string $key, string $default = ''): TextInput => TextInput::make($key)->label($label($key))->numeric()->minValue(0)->step('0.000001')->required()->default($default);
        $phase = fn (): array => [
            isset($context['work_order_id']) ? Hidden::make('work_order_id')->default($context['work_order_id']) : Select::make('work_order_id')->label('Intervención / orden de trabajo')->relationship('workOrder', 'reference', fn (Builder $query) => $query->where('purpose', 'intervention'))->searchable()->preload()->live()->required()
                ->afterStateUpdated(function (Set $set, mixed $state): void {
                    $phases = WorkOrderPhase::where('work_order_id', $state)->where('status', '!=', 'cancelled')->limit(2)->pluck('id');
                    $set('work_order_phase_id', $phases->count() === 1 ? $phases->first() : null);
                }),
            isset($context['work_order_phase_id']) ? Hidden::make('work_order_phase_id')->default($context['work_order_phase_id']) : Select::make('work_order_phase_id')->label($label('work_order_phase_id'))->relationship('workOrderPhase', 'name', fn (Builder $query, Get $get) => $query->where('work_order_id', $get('work_order_id')))->searchable()->preload()->required(),
        ];
        $worker = fn (): array => [
            Select::make('employee_id')->label($label('employee_id'))->relationship('employee', 'name', fn (Builder $query) => $query->where('is_active', true))->searchable()->preload()->helperText(__('costing.worker_help')),
            Select::make('contractor_id')->label($label('contractor_id'))->relationship('contractor', 'legal_name', fn (Builder $query) => $query->where('is_active', true)->whereHas('partyRoles', fn (Builder $roles) => $roles->where('role', 'subcontractor')))->searchable()->preload(),
        ];
        $fields = match ($type) {
            'warehouse' => [TextInput::make('code')->label($label('code'))->required()->maxLength(50), TextInput::make('name')->label($label('name'))->required()->maxLength(255), Toggle::make('is_active')->label($label('is_active'))->default(true)],
            'note' => [Select::make('purchase_order_id')->label('Pedido de compra (opcional)')->relationship('purchaseOrder', 'reference', fn (Builder $query) => $query->where('status', 'posted'))->searchable()->preload(),
                Select::make('supplier_id')->label($label('supplier_id'))->relationship('supplier', 'legal_name', fn (Builder $query) => $query->where('is_active', true)->whereHas('partyRoles', fn (Builder $roles) => $roles->where('role', 'supplier')))->searchable()->preload()->required(),
                Select::make('warehouse_id')->label($label('warehouse_id'))->relationship('warehouse', 'name', fn (Builder $query) => $query->where('is_active', true))->searchable()->preload()->required(),
                TextInput::make('reference')->label($label('reference'))->required()->maxLength(100), DatePicker::make('received_date')->label($label('received_date'))->default(today('Europe/Madrid'))->required(), Textarea::make('notes')->label($label('notes'))->columnSpanFull()],
            'line' => [
                Select::make('purchase_order_line_id')->label('Línea del pedido (opcional)')->relationship('purchaseOrderLine', 'description', function (Builder $query, Get $get, ?Model $record): void {
                    $orderId = $get('../../purchase_order_id') ?? ($record instanceof PurchaseDeliveryLine ? $record->deliveryNote?->purchase_order_id : null);
                    $query->where('purchase_order_id', $orderId);
                })->searchable()->preload(),
                Select::make('product_id')->label($label('product_id'))->relationship('product', 'name', fn (Builder $query) => $query->where('is_active', true)->where('type', 'material')->where('tracks_stock', true))->searchable()->preload()->required()->live()->helperText(__('costing.stock_product_help'))
                    ->afterStateUpdated(function (mixed $state, Set $set): void {
                        $product = Product::find($state);
                        $set('description', $product?->name);
                        $set('product_unit_id', null);
                    }),
                Select::make('product_unit_id')->label($label('product_unit_id'))->relationship('productUnit', 'name', fn (Builder $query, Get $get) => $query->where('product_id', $get('product_id'))->where('is_active', true)->where('available_for_purchase', true))->searchable()->preload(),
                TextInput::make('description')->label($label('description'))->required()->maxLength(500)->columnSpanFull(), $number('position', '1')->integer(), $number('quantity', '1')->minValue('0.000001'), $number('unit_price'), $number('discount_percentage', '0')->maxValue(100),
            ],
            'consumption' => [...$phase(), Select::make('purchase_delivery_line_id')->label($label('purchase_delivery_line_id'))->relationship('purchaseDeliveryLine', 'description', fn (Builder $query) => $query->whereHas('deliveryNote', fn (Builder $note) => $note->where('status', 'received'))->with('deliveryNote.warehouse', 'deliveryNote.supplier', 'baseUnit')->withSum(['materialConsumptions as allocated_quantity' => fn (Builder $q) => $q->where('status', 'posted')->orWhere(fn (Builder $q) => $q->where('status', 'draft')->whereNull('reverses_id'))], 'quantity'))
                ->getOptionLabelFromRecordUsing(fn (PurchaseDeliveryLine $record): string => $record->deliveryNote->warehouse->name.' · '.$record->deliveryNote->supplier->legal_name.' · '.$record->deliveryNote->reference.' #'.$record->id.' · '.$record->description.' · '.(string) BigDecimal::of($record->base_quantity)->minus((string) ($record->allocated_quantity ?? '0')).' '.$record->baseUnit->symbol)->searchable()->preload()->required()->helperText(__('costing.stock_product_help')),
                DatePicker::make('consumed_date')->label($label('consumed_date'))->default(today('Europe/Madrid'))->required(), $number('quantity', '1')->minValue('0.000001'), Textarea::make('notes')->label($label('notes'))->columnSpanFull()],
            'rate' => [...$worker(), $number('hourly_cost'), DatePicker::make('valid_from')->label($label('valid_from'))->required()->default(today('Europe/Madrid'))->helperText(__('costing.rate_help')), DatePicker::make('valid_until')->label($label('valid_until'))->afterOrEqual('valid_from'), Textarea::make('notes')->label($label('notes'))->columnSpanFull()],
            'time' => [...$phase(), ...$worker(), DateTimePicker::make('started_at')->label($label('started_at'))->timezone('Europe/Madrid')->seconds(false)->required()->helperText(__('costing.time_help')),
                DateTimePicker::make('ended_at')->label($label('ended_at'))->timezone('Europe/Madrid')->seconds(false)->required()->after('started_at'), $number('break_minutes', '0')->integer(), Textarea::make('description')->label($label('description'))->required()->columnSpanFull()],
        };

        return $schema->columns(2)->components($fields);
    }
}
