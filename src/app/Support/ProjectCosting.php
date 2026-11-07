<?php

namespace App\Support;

use App\Models\CompanySetting;
use App\Models\Employee;
use App\Models\EmployeeAbsence;
use App\Models\LaborCostRate;
use App\Models\MaterialConsumption;
use App\Models\Party;
use App\Models\Product;
use App\Models\PurchaseDeliveryLine;
use App\Models\PurchaseDeliveryNote;
use App\Models\PurchaseOrderLine;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Models\WorkOrderPhase;
use App\Models\WorkTimeEntry;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ProjectCosting
{
    public function assertEditable(Model $record): void
    {
        if ($record->getAttribute('status') !== null && $record->status !== 'draft') {
            $this->fail('status', 'immutable');
        }
        if ($record instanceof PurchaseDeliveryLine) {
            if ($record->purchase_order_line_id) {
                $source = PurchaseOrderLine::whereKey($record->purchase_order_line_id)->lockForUpdate()->firstOrFail();
                $unitLabel = $record->product_unit_id ? $record->productUnit->name : $record->product->baseUnit->symbol;
                if ($source->purchase_order_id !== $record->deliveryNote->purchase_order_id || $source->product_id !== $record->product_id || $source->unit_label !== $unitLabel) {
                    $this->fail('purchase_order_line_id', 'owner');
                }
                $received = PurchaseDeliveryLine::where('purchase_order_line_id', $source->id)->whereKeyNot($record->id ?? 0)->whereHas('deliveryNote', fn (Builder $q) => $q->where('status', '!=', 'cancelled'))->pluck('quantity')->reduce(fn (BigDecimal $sum, string $q): BigDecimal => $sum->plus($q), BigDecimal::of(0));
                if ($received->plus($record->quantity)->compareTo($source->quantity) > 0) {
                    $this->fail('quantity', 'quantity');
                }
            }
            $this->assertEditable($record->deliveryNote()->firstOrFail());
        }
    }

    public function assertDeletable(Model $record): void
    {
        $this->assertEditable($record);
        if ($record instanceof Warehouse && $record->purchaseDeliveryNotes()->exists() || $record instanceof PurchaseDeliveryNote && $record->lines()->exists() || $record instanceof LaborCostRate && $record->workTimeEntries()->exists()) {
            $this->fail('id', 'referenced');
        }
    }

    public function lockContext(Model $record): void
    {
        if ($record instanceof PurchaseDeliveryLine) {
            $this->assertEditable($record->deliveryNote()->lockForUpdate()->firstOrFail());
        }
        if ($record instanceof MaterialConsumption && $record->purchase_delivery_line_id) {
            $record->purchaseDeliveryLine()->lockForUpdate()->firstOrFail();
        }
        if (($record instanceof LaborCostRate || $record instanceof WorkTimeEntry) && ($record->employee_id || $record->contractor_id)) {
            $record->employee_id ? Employee::whereKey($record->employee_id)->lockForUpdate()->firstOrFail() : Party::whereKey($record->contractor_id)->lockForUpdate()->firstOrFail();
        }
    }

    public function validate(Model $record): void
    {
        $rules = match (true) {
            $record instanceof Warehouse => ['code' => ['required', 'string', 'max:50', Rule::unique('warehouses', 'code')->ignore($record->id)], 'name' => ['required', 'string', 'max:255'], 'is_active' => ['required', 'boolean']],
            $record instanceof PurchaseDeliveryNote => ['supplier_id' => ['required', 'exists:parties,id'], 'warehouse_id' => ['required', 'exists:warehouses,id'], 'reference' => ['required', 'string', 'max:100', Rule::unique('purchase_delivery_notes', 'reference')->where('supplier_id', $record->supplier_id)->ignore($record->id)], 'received_date' => ['required', 'date'], 'currency_code' => ['required', 'in:EUR'], 'status' => ['required', 'in:draft,received,cancelled']],
            $record instanceof PurchaseDeliveryLine => ['purchase_delivery_note_id' => ['required', 'exists:purchase_delivery_notes,id'], 'product_id' => ['required', 'exists:products,id'], 'product_unit_id' => ['nullable', 'exists:product_units,id'], 'description' => ['required', 'string', 'max:500'], 'quantity' => ['required', 'numeric', 'decimal:0,6', 'gt:0', 'max:999999999999'], 'unit_price' => ['required', 'numeric', 'decimal:0,6', 'min:0', 'max:999999999999'], 'discount_percentage' => ['required', 'numeric', 'between:0,100'], 'position' => ['required', 'integer', 'min:1']],
            $record instanceof MaterialConsumption => ['purchase_delivery_line_id' => ['required', 'exists:purchase_delivery_lines,id'], 'work_order_id' => ['required', 'exists:work_orders,id'], 'work_order_phase_id' => ['required', 'exists:work_order_phases,id'], 'consumed_date' => ['required', 'date'], 'quantity' => ['required', 'numeric', 'decimal:0,6', $record->reverses_id ? 'lt:0' : 'gt:0', 'max:999999999999'], 'status' => ['required', 'in:draft,posted,cancelled']],
            $record instanceof LaborCostRate => ['employee_id' => ['nullable', 'exists:employees,id'], 'contractor_id' => ['nullable', 'exists:parties,id'], 'hourly_cost' => ['required', 'numeric', 'decimal:0,6', 'min:0', 'max:999999999999'], 'currency_code' => ['required', 'in:EUR'], 'valid_from' => ['required', 'date'], 'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from']],
            $record instanceof WorkTimeEntry => ['work_order_id' => ['required', 'exists:work_orders,id'], 'work_order_phase_id' => ['required', 'exists:work_order_phases,id'], 'employee_id' => ['nullable', 'exists:employees,id'], 'contractor_id' => ['nullable', 'exists:parties,id'], 'started_at' => ['required', 'date'], 'ended_at' => ['required', 'date', 'after:started_at'], 'break_minutes' => ['required', 'integer', 'min:0'], 'description' => ['required', 'string'], 'status' => ['required', 'in:draft,posted,cancelled']],
        };
        Validator::make($record->getAttributes(), $rules, [], __('costing.fields'))->validate();
        if ($record->exists && $record->getRawOriginal('status') !== null && $record->getRawOriginal('status') !== 'draft') {
            $this->fail('status', 'immutable');
        }
        if ($record->getAttribute('status') === 'cancelled') {
            return;
        }
        if ($record instanceof PurchaseDeliveryNote) {
            if ($record->purchase_order_id && ($record->purchaseOrder->status !== 'posted' || $record->purchaseOrder->supplier_id !== $record->supplier_id)) {
                $this->fail('supplier_id', 'supplier');
            }
            if (! $record->supplier->partyRoles()->where('role', 'supplier')->exists()) {
                $this->fail('supplier_id', 'supplier');
            }
            if ($record->exists && $record->lines()->exists() && $record->isDirty(['supplier_id', 'warehouse_id', 'currency_code'])) {
                $this->fail('reference', 'header_locked');
            }
        }
        if ($record instanceof PurchaseDeliveryLine) {
            $this->assertEditable($record->deliveryNote()->firstOrFail());
            if ($record->exists && $record->isDirty('purchase_delivery_note_id')) {
                $this->fail('purchase_delivery_note_id', 'owner');
            }
            if ($record->product->type !== 'material' || ! $record->product->tracks_stock || ! $record->product->is_active) {
                $this->fail('product_id', 'stock_product');
            }
            if ($record->product_unit_id && ($record->productUnit->product_id !== $record->product_id || ! $record->productUnit->available_for_purchase || ! $record->productUnit->is_active)) {
                $this->fail('product_unit_id', 'owner');
            }
        }
        if ($record instanceof MaterialConsumption || $record instanceof WorkTimeEntry) {
            $this->validatePhase($record);
        }
        if ($record instanceof MaterialConsumption && $record->status !== 'cancelled') {
            $line = $record->purchaseDeliveryLine()->firstOrFail();
            if ($line->deliveryNote->status !== 'received' || $line->deliveryNote->received_date->toDateString() > $record->consumed_date->toDateString()) {
                $this->fail('purchase_delivery_line_id', 'unreceived');
            }
            if ($record->reverses_id) {
                $original = MaterialConsumption::findOrFail($record->reverses_id);
                if ($original->status !== 'posted' || $original->reverses_id || $original->purchase_delivery_line_id !== $record->purchase_delivery_line_id || $original->work_order_phase_id !== $record->work_order_phase_id || $record->consumed_date->lt($original->consumed_date)) {
                    $this->fail('quantity', 'owner');
                }
                $returned = MaterialConsumption::where('reverses_id', $original->id)->whereKeyNot($record->id ?? 0)->whereIn('status', ['draft', 'posted'])->pluck('quantity')->reduce(fn (BigDecimal $sum, string $qty): BigDecimal => $sum->plus($qty), BigDecimal::of('0'));
                if (BigDecimal::of($original->quantity)->plus($returned)->plus($record->quantity)->compareTo('0') < 0) {
                    $this->fail('quantity', 'over_return');
                }
            } elseif (BigDecimal::of($record->quantity)->compareTo($this->available($line, true, $record->id)) > 0) {
                $this->fail('quantity', 'stock');
            }
        }
        if ($record instanceof LaborCostRate || $record instanceof WorkTimeEntry) {
            $this->validateWorker($record);
        }
        if ($record instanceof LaborCostRate) {
            $query = LaborCostRate::whereKeyNot($record->id ?? 0)->where($record->employee_id ? 'employee_id' : 'contractor_id', $record->employee_id ?: $record->contractor_id)
                ->where(fn (Builder $q) => $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', $record->valid_from->toDateString()));
            if ($record->valid_until) {
                $query->whereDate('valid_from', '<=', $record->valid_until->toDateString());
            }
            if ($query->exists()) {
                $this->fail('valid_from', 'rate_overlap');
            }
            if ($record->exists && $record->workTimeEntries()->where('status', 'posted')->exists()) {
                if ($record->isDirty(['employee_id', 'contractor_id', 'hourly_cost', 'currency_code', 'valid_from'])) {
                    $this->fail('hourly_cost', 'rate_used');
                }
                if ($record->valid_until && $record->workTimeEntries()->where('status', 'posted')->get()->contains(fn (WorkTimeEntry $entry): bool => $entry->ended_at->copy()->subSecond()->setTimezone('Europe/Madrid')->toDateString() > $record->valid_until->toDateString())) {
                    $this->fail('valid_until', 'rate_used');
                }
            }
        }
        if ($record instanceof WorkTimeEntry) {
            if (auth()->user()?->role === 'worker' && $record->employee_id !== Employee::where('user_id', auth()->id())->value('id')) {
                $this->fail('employee_id', 'worker');
            }
            $seconds = (int) $record->started_at->diffInSeconds($record->ended_at);
            if ($seconds > 86400 || $seconds <= $record->break_minutes * 60) {
                $this->fail('ended_at', 'duration');
            }
        }
    }

    private function validatePhase(Model $record): void
    {
        $phase = WorkOrderPhase::findOrFail($record->work_order_phase_id);
        if ($phase->workOrder->purpose === 'measurement') {
            throw ValidationException::withMessages(['work_order_id' => 'La medición prepara el presupuesto. Cree una intervención para registrar horas o consumos.']);
        }
        if (auth()->check() && ! OperationalAccess::scope(WorkOrderPhase::query())->whereKey($phase->id)->exists()) {
            abort(403);
        }
        if ($phase->work_order_id !== $record->work_order_id || (! $record->getAttribute('reverses_id') && ($phase->status === 'cancelled' || $phase->workOrder->status === 'cancelled')) || $phase->workOrder->project->currency_code !== 'EUR') {
            $this->fail('work_order_phase_id', 'owner');
        }
        if ($record->exists && $record->isDirty(['work_order_id', 'work_order_phase_id', 'purchase_delivery_line_id', 'reverses_id'])) {
            $this->fail('work_order_phase_id', 'owner');
        }
    }

    private function validateWorker(Model $record): void
    {
        if ((bool) $record->employee_id === (bool) $record->contractor_id) {
            $this->fail('employee_id', 'worker');
        }
        if ($record->contractor_id && ! Party::findOrFail($record->contractor_id)->partyRoles()->where('role', 'subcontractor')->exists()) {
            $this->fail('contractor_id', 'contractor');
        }
    }

    public function markup(?Product $product): string
    {
        return (string) ($product?->sales_markup_percentage ?? CompanySetting::where('scope', 'company')->value('sales_markup_percentage') ?? '15');
    }

    /** @return array{cost: string, sale: string}|null */
    public function productQuote(int $productId, string $date, string $factor = '1'): ?array
    {
        $purchase = PurchaseDeliveryLine::where('product_id', $productId)->whereHas('deliveryNote', fn (Builder $query) => $query->where('status', 'received')->whereNull('source_transfer_id')->whereDate('received_date', '<=', $date))
            ->join('purchase_delivery_notes', 'purchase_delivery_notes.id', '=', 'purchase_delivery_lines.purchase_delivery_note_id')->select('purchase_delivery_lines.*')->orderByDesc('purchase_delivery_notes.received_date')->orderByDesc('purchase_delivery_notes.id')->orderByDesc('purchase_delivery_lines.id')->first();
        if (! $purchase) {
            return null;
        }
        $exactCost = BigDecimal::of($purchase->base_unit_cost)->multipliedBy($factor);
        $cost = (string) $exactCost->toScale(6, RoundingMode::HalfUp);

        return ['cost' => $cost, 'sale' => $this->salePrice((string) $exactCost, $this->markup(Product::findOrFail($productId)))];
    }

    public function salePrice(string $cost, string $percentage): string
    {
        return (string) BigDecimal::of($cost)->multipliedBy(BigDecimal::of('100')->plus($percentage))->dividedBy('100', 6, RoundingMode::HalfUp);
    }

    public function receive(PurchaseDeliveryNote $note): PurchaseDeliveryNote
    {
        return DB::transaction(function () use ($note): PurchaseDeliveryNote {
            $note = PurchaseDeliveryNote::whereKey($note->id)->lockForUpdate()->firstOrFail();
            $this->assertEditable($note);
            if (! $note->warehouse->is_active || ! $note->supplier->is_active || $note->received_date->toDateString() > today('Europe/Madrid')->toDateString()) {
                $this->fail('received_date', 'receipt_date');
            }
            $lines = $note->lines()->with('product.baseUnit', 'productUnit')->get();
            if ($lines->isEmpty()) {
                $this->fail('lines', 'empty');
            }
            $total = BigDecimal::of('0');
            foreach ($lines as $line) {
                $this->validate($line);
                $factor = $line->productUnit?->base_unit_factor ?? '1';
                $base = BigDecimal::of($line->quantity)->multipliedBy($factor)->toScale(6, RoundingMode::HalfUp);
                if ($base->compareTo('0') <= 0) {
                    $this->fail('quantity', 'quantity');
                }
                if ($base->compareTo('99999999999999.999999') > 0) {
                    $this->fail('quantity', 'amount');
                }
                $net = InvoiceCalculator::money(BigDecimal::of($line->quantity)->multipliedBy($line->unit_price)->multipliedBy(BigDecimal::of('100')->minus($line->discount_percentage))->dividedBy('100', 12, RoundingMode::HalfUp));
                $cost = (string) BigDecimal::of($net)->dividedBy($base, 12, RoundingMode::HalfUp);
                if (BigDecimal::of($net)->compareTo('9999999999999999.99') > 0 || BigDecimal::of($cost)->compareTo('999999999999.999999999999') > 0) {
                    $this->fail('unit_price', 'amount');
                }
                $markup = $this->markup($line->product);
                $line->forceFill(['base_unit_id' => $line->product->base_unit_id, 'base_unit_factor' => $factor, 'base_quantity' => (string) $base, 'base_unit_cost' => $cost,
                    'net_total' => $net, 'markup_percentage' => $markup, 'suggested_sale_price' => $this->salePrice($cost, $markup),
                    'product_snapshot' => ['reference' => $line->product->reference, 'name' => $line->product->name, 'unit' => $line->product->baseUnit->symbol, 'presentation' => $line->productUnit?->name]])->save();
                $total = $total->plus($net);
            }
            if ($total->compareTo('9999999999999999.99') > 0) {
                $this->fail('lines', 'amount');
            }
            $note->forceFill(['status' => 'received', 'net_total' => InvoiceCalculator::money($total), 'confirmed_at' => now()])->save();

            return $note;
        }, 3);
    }

    public function available(PurchaseDeliveryLine $line, bool $reserveDrafts = true, ?int $except = null, ?int $exceptMovement = null): string
    {
        $query = $line->materialConsumptions()->when($except, fn (Builder $q) => $q->whereKeyNot($except))->where(function (Builder $q) use ($reserveDrafts): void {
            $q->where('status', 'posted');
            if ($reserveDrafts) {
                $q->orWhere(fn (Builder $q) => $q->where('status', 'draft')->whereNull('reverses_id'));
            }
        });
        $used = $query->pluck('quantity')->reduce(fn (BigDecimal $sum, string $qty): BigDecimal => $sum->plus($qty), BigDecimal::of('0'));

        $adjustment = BigDecimal::of(0);
        foreach (StockMovement::where('purchase_delivery_line_id', $line->id)->when($exceptMovement, fn (Builder $q) => $q->whereKeyNot($exceptMovement))->where(fn (Builder $q) => $q->where('status', 'posted')->when($reserveDrafts, fn (Builder $q) => $q->orWhere(fn (Builder $q) => $q->where('status', 'draft')->where('quantity', '<', 0))))->pluck('quantity') as $quantity) {
            $adjustment = $adjustment->plus($quantity);
        }

        return (string) BigDecimal::of($line->base_quantity ?? '0')->plus($adjustment)->minus($used)->toScale(6);
    }

    public function postMaterial(MaterialConsumption $consumption): MaterialConsumption
    {
        return DB::transaction(function () use ($consumption): MaterialConsumption {
            $entry = MaterialConsumption::whereKey($consumption->id)->lockForUpdate()->firstOrFail();
            $this->assertEditable($entry);
            $this->lockContext($entry);
            $this->validate($entry);
            if ($entry->consumed_date->toDateString() > today('Europe/Madrid')->toDateString()) {
                $this->fail('consumed_date', 'future');
            }
            $line = $entry->purchaseDeliveryLine;
            $cost = InvoiceCalculator::money(BigDecimal::of($entry->quantity)->multipliedBy($line->base_unit_cost));
            $markup = $line->markup_percentage;
            $unitSale = $line->suggested_sale_price;
            if ($entry->reverses_id) {
                $original = $entry->reverses;
                $otherReturns = MaterialConsumption::where('reverses_id', $original->id)->whereKeyNot($entry->id)->where('status', 'posted')->get();
                $remaining = BigDecimal::of($original->quantity);
                $remainingCost = BigDecimal::of($original->total_cost);
                $remainingSale = BigDecimal::of($original->sale_total);
                foreach ($otherReturns as $other) {
                    $remaining = $remaining->plus($other->quantity);
                    $remainingCost = $remainingCost->plus($other->total_cost);
                    $remainingSale = $remainingSale->plus($other->sale_total);
                }
                $isLast = BigDecimal::of($entry->quantity)->abs()->compareTo($remaining) === 0;
                $cost = $isLast ? (string) $remainingCost->negated() : InvoiceCalculator::money(BigDecimal::of($original->total_cost)->multipliedBy($entry->quantity)->dividedBy($original->quantity, 12, RoundingMode::HalfUp));
                $returnSale = $isLast ? (string) $remainingSale->negated() : InvoiceCalculator::money(BigDecimal::of($original->sale_total)->multipliedBy($entry->quantity)->dividedBy($original->quantity, 12, RoundingMode::HalfUp));
                $markup = $original->markup_percentage;
                $unitSale = $original->unit_sale_price;
            } elseif (BigDecimal::of($entry->quantity)->compareTo($this->available($line, false)) === 0) {
                $spent = $line->materialConsumptions()->where('status', 'posted')->pluck('total_cost')->reduce(fn (BigDecimal $sum, string $amount): BigDecimal => $sum->plus($amount), BigDecimal::of('0'));
                $cost = InvoiceCalculator::money(BigDecimal::of($line->net_total)->plus(ProjectEconomics::sum(StockMovement::where('purchase_delivery_line_id', $line->id)->where('status', 'posted'), 'total_cost'))->minus($spent));
            }
            $entry->forceFill(['status' => 'posted', 'unit_cost' => $line->base_unit_cost, 'total_cost' => $cost, 'markup_percentage' => $markup, 'unit_sale_price' => $unitSale,
                'sale_total' => $entry->reverses_id ? $returnSale : InvoiceCalculator::money(BigDecimal::of($entry->quantity)->multipliedBy($unitSale)), 'posted_at' => now()])->save();

            return $entry;
        }, 3);
    }

    public function returnMaterial(MaterialConsumption $consumption, ?string $quantity = null): MaterialConsumption
    {
        return DB::transaction(function () use ($consumption, $quantity): MaterialConsumption {
            $original = MaterialConsumption::whereKey($consumption->id)->lockForUpdate()->firstOrFail();
            if ($original->status !== 'posted' || $original->reverses_id) {
                $this->fail('status', 'return');
            }
            $remaining = BigDecimal::of($original->quantity);
            foreach (MaterialConsumption::where('reverses_id', $original->id)->whereIn('status', ['draft', 'posted'])->pluck('quantity') as $returned) {
                $remaining = $remaining->plus($returned);
            }
            if ($remaining->compareTo(0) <= 0) {
                $this->fail('status', 'return');
            }
            $quantity ??= (string) $remaining;
            validator(['quantity' => $quantity], ['quantity' => ['required', 'numeric', 'decimal:0,6', 'gt:0']])->validate();
            $return = new MaterialConsumption($original->only(['purchase_delivery_line_id', 'work_order_id', 'work_order_phase_id']));
            $return->forceFill(['quantity' => (string) BigDecimal::of($quantity)->negated(), 'consumed_date' => today('Europe/Madrid')->toDateString(), 'reverses_id' => $original->id, 'notes' => __('costing.return_note', ['id' => $original->id])]);
            $return->save();

            return $this->postMaterial($return);
        }, 3);
    }

    public function postTime(WorkTimeEntry $entry): WorkTimeEntry
    {
        return DB::transaction(function () use ($entry): WorkTimeEntry {
            $entry = WorkTimeEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();
            $this->assertEditable($entry);
            $this->lockContext($entry);
            $this->validate($entry);
            if ($entry->ended_at->isFuture()) {
                $this->fail('ended_at', 'future');
            }
            $workerField = $entry->employee_id ? 'employee_id' : 'contractor_id';
            $workerId = $entry->$workerField;
            $worker = $entry->employee_id ? $entry->employee : $entry->contractor;
            if ($entry->employee_id && (($worker->employment_start_date && $entry->started_at->copy()->timezone('Europe/Madrid')->toDateString() < $worker->employment_start_date->toDateString()) || ($worker->employment_end_date && $entry->ended_at->copy()->timezone('Europe/Madrid')->toDateString() > $worker->employment_end_date->toDateString()))) {
                $this->fail('employee_id', 'worker');
            }
            if ($entry->employee_id && EmployeeAbsence::where('employee_id', $entry->employee_id)->where('status', 'posted')->whereDate('starts_on', '<=', $entry->ended_at->copy()->timezone('Europe/Madrid')->toDateString())->whereDate('ends_on', '>=', $entry->started_at->copy()->timezone('Europe/Madrid')->toDateString())->exists()) {
                $this->fail('employee_id', 'worker');
            }
            if (! $worker->is_active) {
                $this->fail($workerField, 'worker');
            }
            $assignments = $entry->employee_id ? $entry->workOrder->workOrderEmployeeAssignments() : $entry->workOrder->workOrderContractorAssignments();
            if (! $assignments->where($workerField, $workerId)->where('status', '!=', 'cancelled')->where(fn (Builder $q) => $q->whereNull('work_order_phase_id')->orWhere('work_order_phase_id', $entry->work_order_phase_id))->exists()) {
                $this->fail($workerField, 'assignment');
            }
            if (WorkTimeEntry::whereKeyNot($entry->id)->whereDoesntHave('reversals', fn (Builder $q) => $q->where('status', 'posted'))->where($workerField, $workerId)->where('status', 'posted')->where('started_at', '<', $entry->ended_at)->where('ended_at', '>', $entry->started_at)->exists()) {
                $this->fail('started_at', 'time_overlap');
            }
            $from = $entry->started_at->copy()->setTimezone('Europe/Madrid')->toDateString();
            $until = $entry->ended_at->copy()->subSecond()->setTimezone('Europe/Madrid')->toDateString();
            $rates = LaborCostRate::where($workerField, $workerId)->where('currency_code', 'EUR')->whereDate('valid_from', '<=', $from)->where(fn (Builder $q) => $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', $until))->get();
            if ($rates->count() !== 1) {
                $this->fail('started_at', 'no_rate');
            }
            $rate = $rates->first();
            $seconds = (string) ((int) $entry->started_at->diffInSeconds($entry->ended_at) - $entry->break_minutes * 60);
            $hours = BigDecimal::of($seconds)->dividedBy('3600', 6, RoundingMode::HalfUp);
            $cost = (string) BigDecimal::of($seconds)->multipliedBy($rate->hourly_cost)->dividedBy('3600', 2, RoundingMode::HalfUp);
            $entry->forceFill(['status' => 'posted', 'labor_cost_rate_id' => $rate->id, 'hours' => (string) $hours, 'hourly_cost' => $rate->hourly_cost, 'total_cost' => $cost, 'posted_at' => now()])->save();

            return $entry;
        }, 3);
    }

    public function cancel(Model $record): void
    {
        DB::transaction(function () use ($record): void {
            $record = $record->newQuery()->whereKey($record->id)->lockForUpdate()->firstOrFail();
            $this->assertEditable($record);
            $this->lockContext($record);
            $record->status = 'cancelled';
            $record->save();
        });
    }

    /** @return array{materials: string, labor: string, total: string} */
    public function totals(WorkOrder|WorkOrderPhase $record): array
    {
        $materials = (string) $record->materialConsumptions()->where('status', 'posted')->sum('total_cost');
        $labor = (string) $record->workTimeEntries()->where('status', 'posted')->whereDoesntHave('reversals', fn (Builder $q) => $q->where('status', 'posted'))->sum('total_cost');

        return ['materials' => InvoiceCalculator::money($materials), 'labor' => InvoiceCalculator::money($labor), 'total' => InvoiceCalculator::money(BigDecimal::of($materials)->plus($labor))];
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => __('costing.messages.'.$message)]);
    }
}
