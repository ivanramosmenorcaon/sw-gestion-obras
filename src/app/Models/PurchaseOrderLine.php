<?php

namespace App\Models;

use App\Support\PurchaseDocumentLines;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['purchase_order_id', 'product_id', 'position', 'description', 'unit_label', 'quantity', 'unit_price', 'discount_percentage', 'tax_percentage', 'withholding_percentage', 'retention_percentage'])]
class PurchaseOrderLine extends Model
{
    use HasFactory;

    protected $attributes = ['position' => 1, 'discount_percentage' => '0', 'tax_percentage' => '0', 'withholding_percentage' => '0', 'retention_percentage' => '0'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:6', 'unit_price' => 'decimal:6', 'discount_percentage' => 'decimal:4', 'tax_percentage' => 'decimal:4', 'net_amount' => 'decimal:2', 'tax_amount' => 'decimal:2', 'withholding_amount' => 'decimal:2', 'retention_amount' => 'decimal:2'];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function save(array $options = []): bool
    {
        return app(PurchaseDocumentLines::class)->save($this, fn (): bool => parent::save($options));
    }

    public function delete(): ?bool
    {
        return app(PurchaseDocumentLines::class)->remove($this, fn (): ?bool => parent::delete());
    }
}
