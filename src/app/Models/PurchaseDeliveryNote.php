<?php

namespace App\Models;

use App\Support\ProjectCosting;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['purchase_order_id', 'supplier_id', 'warehouse_id', 'reference', 'received_date', 'currency_code', 'notes'])]
class PurchaseDeliveryNote extends Model
{
    use HasFactory;

    protected $attributes = ['status' => 'draft', 'currency_code' => 'EUR'];

    protected function casts(): array
    {
        return ['received_date' => 'date', 'confirmed_at' => 'datetime', 'net_total' => 'decimal:2'];
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

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'supplier_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseDeliveryLine::class, 'purchase_delivery_note_id');
    }
}
