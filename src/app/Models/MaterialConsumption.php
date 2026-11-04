<?php

namespace App\Models;

use App\Support\ProjectCosting;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['purchase_delivery_line_id', 'work_order_id', 'work_order_phase_id', 'consumed_date', 'quantity', 'notes'])]
class MaterialConsumption extends Model
{
    use HasFactory;

    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return ['consumed_date' => 'date', 'quantity' => 'decimal:6', 'unit_cost' => 'decimal:12', 'total_cost' => 'decimal:2', 'unit_sale_price' => 'decimal:6', 'sale_total' => 'decimal:2', 'markup_percentage' => 'decimal:4', 'posted_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(fn (self $record) => app(ProjectCosting::class)->validate($record));
        static::deleting(function (self $record): void {
            app(ProjectCosting::class)->assertDeletable($record);
        });
    }

    public function save(array $options = []): bool
    {
        return $this->getConnection()->transaction(function () use ($options): bool {
            if ($this->exists) {
                $stored = $this->newQuery()->whereKey($this->id)->lockForUpdate()->firstOrFail();
                app(ProjectCosting::class)->assertEditable($stored);
            }
            app(ProjectCosting::class)->lockContext($this);

            return parent::save($options);
        }, 3);
    }

    public function delete(): ?bool
    {
        return $this->getConnection()->transaction(function (): ?bool {
            $stored = $this->newQuery()->whereKey($this->id)->lockForUpdate()->firstOrFail();
            app(ProjectCosting::class)->assertDeletable($stored);
            app(ProjectCosting::class)->lockContext($this);

            return parent::delete();
        });
    }

    public function purchaseDeliveryLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseDeliveryLine::class, 'purchase_delivery_line_id');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'work_order_id');
    }

    public function workOrderPhase(): BelongsTo
    {
        return $this->belongsTo(WorkOrderPhase::class, 'work_order_phase_id');
    }

    public function reverses(): BelongsTo
    {
        return $this->belongsTo(MaterialConsumption::class, 'reverses_id');
    }
}
