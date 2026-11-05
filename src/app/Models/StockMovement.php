<?php

namespace App\Models;

use App\Support\StockMovements;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['purchase_delivery_line_id', 'warehouse_id', 'reference', 'category', 'description', 'quantity', 'operation_date'])]
class StockMovement extends Model
{
    use HasFactory;

    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:6', 'total_cost' => 'decimal:2', 'operation_date' => 'date'];
    }

    public function purchaseDeliveryLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseDeliveryLine::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function save(array $options = []): bool
    {
        return app(StockMovements::class)->save($this, fn (): bool => parent::save($options));
    }
}
