<?php

namespace App\Support;

use App\Models\ProjectItem;
use App\Models\WorkOrder;
use App\Models\WorkOrderLine;
use App\Models\WorkProgress;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProjectScope
{
    public const SNAPSHOT_FIELDS = ['project_chapter_id', 'source_code', 'type', 'product_id', 'product_unit_id', 'description', 'unit_id', 'unit_label', 'base_unit_id', 'base_unit_factor', 'estimated_unit_cost', 'estimated_unit_sale_price', 'discount_percentage', 'measurement_details', 'budget_breakdown'];

    public function remaining(ProjectItem $item, ?int $excludingLine = null): string
    {
        $used = BigDecimal::of(0);
        foreach ($item->workOrderLines()->when($excludingLine, fn (Builder $q) => $q->whereKeyNot($excludingLine))->whereHas('workOrder', fn (Builder $q) => $q->where('status', '!=', 'cancelled')->where('purpose', 'intervention'))->pluck('planned_quantity') as $quantity) {
            $used = $used->plus((string) $quantity);
        }

        return (string) BigDecimal::of($item->planned_quantity)->minus($used)->toScale(6);
    }

    public function prepareAllocation(WorkOrderLine $line): void
    {
        $order = WorkOrder::whereKey($line->work_order_id)->lockForUpdate()->first();
        $item = ProjectItem::whereKey($line->project_item_id)->lockForUpdate()->first();
        if (! $item || ! $order || $item->project_id !== $order->project_id || ($line->project_id && (int) $line->project_id !== $item->project_id)) {
            throw ValidationException::withMessages(['project_item_id' => 'La partida y la intervención deben pertenecer a la misma obra.']);
        }
        if (auth()->check() && (! OperationalAccess::allows(ProjectItem::class, 'view', $item) || ! OperationalAccess::allows(WorkOrder::class, 'update', $order))) {
            abort(403);
        }
        if ((! $line->exists || $line->isDirty('planned_quantity')) && (in_array($order->status, ['completed', 'cancelled'], true) || in_array($order->project->status, ['completed', 'cancelled'], true))) {
            throw ValidationException::withMessages(['planned_quantity' => 'No se puede cambiar el alcance de una intervención u obra cerrada.']);
        }
        if ($line->exists && $line->isDirty(['project_item_id', 'work_order_id', 'project_id'])) {
            throw ValidationException::withMessages(['project_item_id' => 'No se puede trasladar una asignación existente a otra partida u orden.']);
        }
        if ($line->exists && $line->isDirty(self::SNAPSHOT_FIELDS)) {
            throw ValidationException::withMessages(['estimated_unit_sale_price' => 'El alcance, unidad y precio proceden de la partida de obra. Cambie únicamente la cantidad encargada.']);
        }
        if (! $line->exists) {
            $line->fill($item->only(self::SNAPSHOT_FIELDS));
            $line->project_id = $item->project_id;
        }
        validator($line->getAttributes(), ['planned_quantity' => ['required', 'numeric', 'decimal:0,6', 'min:0', 'max:99999999999999.999999']])->validate();
        if ($order->purpose === 'intervention' && $order->status !== 'cancelled' && BigDecimal::of($line->planned_quantity)->compareTo($this->remaining($item, $line->id)) > 0) {
            throw ValidationException::withMessages(['planned_quantity' => 'Supera la cantidad pendiente de encargar de esta partida. Reduzca otra orden o apruebe una ampliación.']);
        }
        if ($line->exists) {
            $executed = BigDecimal::of(0);
            foreach (WorkProgress::where('work_order_line_id', $line->id)->where('status', '!=', 'cancelled')->pluck('quantity') as $quantity) {
                $executed = $executed->plus((string) $quantity);
            }
            $billed = BigDecimal::of(WorkOrderLine::findOrFail($line->id)->planned_quantity)->minus(app(SalesBilling::class)->remaining($line->id));
            if (BigDecimal::of($line->planned_quantity)->compareTo($executed) < 0 || BigDecimal::of($line->planned_quantity)->compareTo($billed) < 0) {
                throw ValidationException::withMessages(['planned_quantity' => 'La cantidad no puede ser inferior a lo ejecutado o reservado en facturación.']);
            }
        }
    }

    public function captureLine(WorkOrderLine $line): ProjectItem
    {
        return DB::transaction(function () use ($line): ProjectItem {
            $order = WorkOrder::whereKey($line->work_order_id)->lockForUpdate()->firstOrFail();
            $stored = WorkOrderLine::whereKey($line->id)->lockForUpdate()->firstOrFail();
            if ($stored->project_item_id) {
                $line->setRawAttributes($stored->getAttributes(), sync: true);

                return $stored->projectItem;
            }
            $item = ProjectItem::create($stored->only(array_merge(self::SNAPSHOT_FIELDS, ['project_id', 'position', 'planned_quantity', 'notes'])));
            $updates = ['project_item_id' => $item->id];
            if (! $stored->work_order_phase_id && $order->purpose === 'intervention') {
                $phase = $order->workOrderPhases()->where('status', '!=', 'cancelled')->orderBy('position')->first()
                    ?? $order->workOrderPhases()->create(['code' => 'GENERAL', 'name' => 'Trabajo general', 'position' => 1]);
                $updates['work_order_phase_id'] = $phase->id;
            }
            DB::table('work_order_lines')->where('id', $stored->id)->update($updates);
            $line->setRawAttributes(array_replace($stored->getAttributes(), $updates), sync: true);

            return $item;
        }, 3);
    }

    public function validateOrderChange(WorkOrder $order): void
    {
        if (! $order->exists) {
            return;
        }
        if ($order->isDirty('purpose')) {
            throw ValidationException::withMessages(['purpose' => 'El origen de una medición o intervención no se modifica después de crearla.']);
        }
        if (! $order->isDirty('status')) {
            return;
        }
        if ($order->status === 'cancelled' && ($order->workTimeEntries()->where('status', 'posted')->exists() || $order->materialConsumptions()->where('status', 'posted')->exists()
            || WorkProgress::whereHas('workOrderLine', fn (Builder $q) => $q->where('work_order_id', $order->id))->where('status', 'posted')->exists()
            || $order->salesInvoices()->whereIn('status', ['draft', 'issued'])->exists())) {
            throw ValidationException::withMessages(['status' => 'La intervención tiene ejecución o facturación. Resuelva su histórico antes de cancelarla.']);
        }
        if ($order->getRawOriginal('status') === 'cancelled' && $order->status !== 'cancelled') {
            $lines = $order->workOrderLines()->whereNotNull('project_item_id')->get()->groupBy('project_item_id');
            foreach (ProjectItem::whereKey($lines->keys())->orderBy('id')->lockForUpdate()->get() as $item) {
                $requested = BigDecimal::of(0);
                foreach ($lines[$item->id] as $line) {
                    foreach (['description', 'unit_id', 'unit_label', 'estimated_unit_sale_price', 'discount_percentage'] as $field) {
                        if ((string) $line->getAttribute($field) !== (string) $item->getAttribute($field)) {
                            throw ValidationException::withMessages(['status' => 'La partida tiene un acuerdo actualizado. Cree una nueva asignación antes de reabrir esta intervención.']);
                        }
                    }
                    $requested = $requested->plus($line->planned_quantity);
                }
                if ($requested->compareTo($this->remaining($item)) > 0) {
                    throw ValidationException::withMessages(['status' => 'La cantidad de esta intervención ya está encargada en otras órdenes. Ajuste el reparto antes de reabrirla.']);
                }
            }
        }
    }

    public function assign(WorkOrder $order, ProjectItem $item, string $quantity): WorkOrderLine
    {
        return DB::transaction(function () use ($order, $item, $quantity): WorkOrderLine {
            $order = WorkOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->purpose !== 'intervention' || in_array($order->status, ['completed', 'cancelled'], true) || in_array($order->project->status, ['completed', 'cancelled'], true)) {
                throw ValidationException::withMessages(['work_order_id' => 'Seleccione una intervención abierta de una obra abierta.']);
            }
            abort_unless(OperationalAccess::allows(WorkOrder::class, 'update', $order) && OperationalAccess::allows(WorkOrderLine::class, 'create') && OperationalAccess::allows(ProjectItem::class, 'view', $item), 403);
            validator(['planned_quantity' => $quantity], ['planned_quantity' => ['required', 'numeric', 'decimal:0,6', 'gt:0', 'max:99999999999999.999999']])->validate();
            $phase = $order->workOrderPhases()->where('status', '!=', 'cancelled')->orderBy('position')->first();
            if (! $phase) {
                $phase = $order->workOrderPhases()->create(['code' => 'GENERAL', 'name' => 'Trabajo general', 'position' => 1]);
            }

            return $order->workOrderLines()->create([
                'project_id' => $order->project_id, 'project_item_id' => $item->id,
                'work_order_phase_id' => $phase->id, 'position' => ($order->workOrderLines()->max('position') ?? 0) + 1,
                'planned_quantity' => $quantity,
            ]);
        }, 3);
    }
}
