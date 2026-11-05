<?php

namespace App\Support;

use App\Models\PurchaseDeliveryLine;
use App\Models\PurchaseDeliveryNote;
use App\Models\StockMovement;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StockMovements
{
    public function save(StockMovement $movement, Closure $save): bool
    {
        return DB::transaction(function () use ($movement, $save): bool {
            $line = PurchaseDeliveryLine::whereKey($movement->purchase_delivery_line_id)->lockForUpdate()->firstOrFail();
            if ($movement->exists && StockMovement::whereKey($movement->id)->lockForUpdate()->firstOrFail()->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'El movimiento confirmado es inmutable. Registre un ajuste inverso.']);
            }
            if (auth()->check() && ! in_array(auth()->user()->role, ['administrator', 'office'], true)) {
                abort(403);
            }
            validator($movement->getAttributes(), [
                'reference' => ['required', 'string', 'max:100'], 'description' => ['required', 'string'],
                'category' => ['required', 'in:adjustment,transfer,supplier_return'],
                'quantity' => ['required', 'numeric', 'decimal:0,6', 'not_in:0', 'between:-999999999999,999999999999'],
                'operation_date' => ['required', 'date'], 'status' => ['required', 'in:draft,posted,cancelled'],
            ])->validate();
            $quantity = BigDecimal::of($movement->quantity);
            if ($quantity->isZero() || $movement->operation_date->toDateString() < $line->deliveryNote->received_date->toDateString()) {
                throw ValidationException::withMessages(['quantity' => 'La cantidad debe ser distinta de cero y la fecha posterior o igual a la recepción.']);
            }
            if ($line->deliveryNote->status !== 'received' || ($movement->category !== 'adjustment' && ! $quantity->isNegative())) {
                throw ValidationException::withMessages(['quantity' => 'Seleccione un lote recibido e indique cantidad negativa para traslados o devoluciones a proveedor.']);
            }
            if ($quantity->isNegative() && $quantity->abs()->compareTo(app(ProjectCosting::class)->available($line, true, null, $movement->id)) > 0) {
                throw ValidationException::withMessages(['quantity' => 'Supera las existencias disponibles después de reservas.']);
            }
            if ($movement->category === 'transfer' && (! $movement->warehouse_id || ! $movement->warehouse->is_active || $movement->warehouse_id === $line->deliveryNote->warehouse_id)) {
                throw ValidationException::withMessages(['warehouse_id' => 'Seleccione un almacén de destino activo y distinto del origen.']);
            }
            if ($movement->status === 'posted' && $movement->operation_date->toDateString() > today('Europe/Madrid')->toDateString()) {
                throw ValidationException::withMessages(['operation_date' => 'No puede confirmar un movimiento futuro.']);
            }
            $movement->total_cost = InvoiceCalculator::money($quantity->multipliedBy($line->base_unit_cost));
            $result = $save();
            if ($movement->status === 'posted' && $movement->category === 'transfer' && ! $movement->target_line_id) {
                $note = new PurchaseDeliveryNote([
                    'supplier_id' => $line->deliveryNote->supplier_id, 'warehouse_id' => $movement->warehouse_id,
                    'reference' => 'TRF-'.$movement->id, 'received_date' => $movement->operation_date,
                    'notes' => 'Traslado interno '.$movement->reference.'. Procedencia: '.$line->deliveryNote->reference.'. No es una compra nueva.',
                ]);
                $note->forceFill(['source_transfer_id' => $movement->id])->save();
                $target = $note->lines()->create([
                    'product_id' => $line->product_id, 'description' => $line->description, 'position' => 1,
                    'quantity' => (string) $quantity->abs(), 'unit_price' => (string) BigDecimal::of($line->base_unit_cost)->toScale(6, RoundingMode::HalfUp),
                    'discount_percentage' => '0',
                ]);
                app(ProjectCosting::class)->receive($note);
                DB::table('purchase_delivery_lines')->where('id', $target->id)->update([
                    'net_total' => (string) BigDecimal::of($movement->total_cost)->negated(), 'base_unit_cost' => $line->base_unit_cost,
                    'markup_percentage' => $line->markup_percentage, 'suggested_sale_price' => $line->suggested_sale_price,
                ]);
                DB::table('purchase_delivery_notes')->where('id', $note->id)->update(['net_total' => (string) BigDecimal::of($movement->total_cost)->negated()]);
                DB::table('stock_movements')->where('id', $movement->id)->update(['target_line_id' => $target->id]);
            }

            return $result;
        }, 3);
    }
}
