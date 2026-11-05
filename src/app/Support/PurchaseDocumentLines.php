<?php

namespace App\Support;

use App\Models\PurchaseDeliveryLine;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PurchaseDocumentLines
{
    private function parent(Model $line): PurchaseOrder|PurchaseInvoice
    {
        return $line instanceof PurchaseOrderLine ? PurchaseOrder::whereKey($line->purchase_order_id)->lockForUpdate()->firstOrFail() : PurchaseInvoice::whereKey($line->purchase_invoice_id)->lockForUpdate()->firstOrFail();
    }

    private function assertDraft(PurchaseOrder|PurchaseInvoice $parent): void
    {
        if ($parent->status !== 'draft') {
            throw ValidationException::withMessages(['lines' => 'El documento ya está confirmado. Sus líneas no se pueden modificar.']);
        }
        if (auth()->check() && ! OperationalAccess::allows($parent::class, 'update', $parent)) {
            abort(403);
        }
    }

    public function save(Model $line, Closure $save): bool
    {
        return DB::transaction(function () use ($line, $save): bool {
            $parent = $this->parent($line);
            $this->assertDraft($parent);
            if ($line->exists && $line->isDirty($line instanceof PurchaseOrderLine ? 'purchase_order_id' : 'purchase_invoice_id')) {
                throw ValidationException::withMessages(['lines' => 'No se puede trasladar una línea a otro documento.']);
            }
            validator($line->getAttributes(), [
                'product_id' => ['nullable', 'integer', 'exists:products,id'],
                'description' => ['required', 'string', 'max:1000'],
                'unit_label' => ['required', 'string', 'max:100'],
                'position' => ['required', 'integer', 'min:1'],
                'quantity' => ['required', 'numeric', 'decimal:0,6', 'gt:0', 'max:999999999999'],
                'unit_price' => ['required', 'numeric', 'decimal:0,6', 'min:0', 'max:999999999999'],
                'discount_percentage' => ['required', 'numeric', 'decimal:0,4', 'between:0,100'],
                'tax_percentage' => ['required', 'numeric', 'decimal:0,4', 'between:0,100'],
                'withholding_percentage' => ['required', 'numeric', 'decimal:0,4', 'between:0,100'],
                'retention_percentage' => ['required', 'numeric', 'decimal:0,4', 'between:0,100'],
            ])->validate();
            if ($line instanceof PurchaseInvoiceLine) {
                foreach (['purchase_order_line_id' => PurchaseOrderLine::class, 'purchase_delivery_line_id' => PurchaseDeliveryLine::class] as $key => $model) {
                    if (! $line->$key) {
                        continue;
                    }
                    $source = $model::findOrFail($line->$key);
                    $owner = $source instanceof PurchaseOrderLine ? $source->purchaseOrder : $source->deliveryNote;
                    if (($owner->supplier_id !== $parent->supplier_id) || ($owner->project_id && $owner->project_id !== $parent->project_id) || ($line->product_id && $source->product_id !== $line->product_id)) {
                        throw ValidationException::withMessages(['lines' => 'El producto, proveedor y obra deben coincidir con el origen.']);
                    }
                }
            }
            $line->net_amount = InvoiceCalculator::money(BigDecimal::of($line->quantity)->multipliedBy($line->unit_price)->multipliedBy(BigDecimal::of(100)->minus($line->discount_percentage))->dividedBy(100, 12, RoundingMode::HalfUp));
            if (BigDecimal::of($line->net_amount)->compareTo('9999999999999999.99') > 0) {
                throw ValidationException::withMessages(['unit_price' => 'El importe neto supera el límite admitido.']);
            }
            foreach (['tax', 'withholding', 'retention'] as $type) {
                $line->{$type.'_amount'} = InvoiceCalculator::percentage($line->net_amount, (string) $line->{$type.'_percentage'});
            }
            $result = $save();
            $this->recalculate($parent);

            return $result;
        }, 3);
    }

    public function remove(Model $line, Closure $delete): ?bool
    {
        return DB::transaction(function () use ($line, $delete): ?bool {
            $parent = $this->parent($line);
            $this->assertDraft($parent);
            $result = $delete();
            $this->recalculate($parent);

            return $result;
        }, 3);
    }

    public function recalculate(PurchaseOrder|PurchaseInvoice $parent): void
    {
        $totals = [];
        foreach (['net_amount', 'tax_amount', 'withholding_amount', 'retention_amount'] as $key) {
            $totals[$key] = ProjectEconomics::sum($parent->lines(), $key);
        }
        $payable = InvoiceCalculator::money(BigDecimal::of($totals['net_amount'])->plus($totals['tax_amount'])->minus($totals['withholding_amount'])->minus($totals['retention_amount']));
        if (BigDecimal::of($payable)->isNegative()) {
            throw ValidationException::withMessages(['lines' => 'Las retenciones superan el importe de la factura.']);
        }
        $changes = $parent instanceof PurchaseOrder ? ['amount' => $totals['net_amount']] : [...$totals, 'payable_total' => $payable];
        DB::table($parent->getTable())->where('id', $parent->id)->update($changes);
    }
}
