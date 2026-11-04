<?php

namespace App\Models;

use App\Support\ProjectCosting;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['purchase_delivery_note_id', 'product_id', 'product_unit_id', 'description', 'position', 'quantity', 'unit_price', 'discount_percentage'])]
class PurchaseDeliveryLine extends Model
{
    use HasFactory;

    protected $attributes = ['position' => 1, 'discount_percentage' => '0'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:6', 'unit_price' => 'decimal:6', 'discount_percentage' => 'decimal:4', 'base_unit_factor' => 'decimal:12', 'base_quantity' => 'decimal:6', 'base_unit_cost' => 'decimal:12', 'net_total' => 'decimal:2', 'markup_percentage' => 'decimal:4', 'suggested_sale_price' => 'decimal:6', 'product_snapshot' => 'array'];
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

    public function deliveryNote(): BelongsTo
    {
        return $this->belongsTo(PurchaseDeliveryNote::class, 'purchase_delivery_note_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'product_unit_id');
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function materialConsumptions(): HasMany
    {
        return $this->hasMany(MaterialConsumption::class, 'purchase_delivery_line_id');
    }
}
