<?php

namespace App\Models;

use App\Support\SalesBilling;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesInvoiceLine extends Model
{
    use HasFactory;

    protected $fillable = ['sales_invoice_id', 'product_id', 'position', 'description', 'unit_label', 'quantity', 'unit_price', 'discount_percentage', 'tax_profile_id', 'withholding_rate_id'];
    protected $attributes = ['discount_percentage' => '0', 'position' => 1];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:6', 'unit_price' => 'decimal:6', 'discount_percentage' => 'decimal:4', 'fiscal_snapshot' => 'array', 'net_amount' => 'decimal:2', 'tax_amount' => 'decimal:2', 'surcharge_amount' => 'decimal:2', 'withholding_amount' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::saving(fn (self $line) => app(SalesBilling::class)->validateLine($line));
        static::deleting(fn (self $line) => app(SalesBilling::class)->assertDraft($line->invoice()->firstOrFail()));
    }

    public function save(array $options = []): bool
    {
        return $this->getConnection()->transaction(function () use ($options): bool {
            $invoice = $this->invoice()->lockForUpdate()->firstOrFail();
            app(SalesBilling::class)->assertDraft($invoice);
            return parent::save($options);
        });
    }

    public function delete(): ?bool
    {
        return $this->getConnection()->transaction(function (): ?bool {
            app(SalesBilling::class)->assertDraft($this->invoice()->lockForUpdate()->firstOrFail());
            return parent::delete();
        });
    }

    public function invoice(): BelongsTo { return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function taxProfile(): BelongsTo { return $this->belongsTo(TaxProfile::class); }
    public function withholdingRate(): BelongsTo { return $this->belongsTo(TaxRate::class); }
    public function workOrderLine(): BelongsTo { return $this->belongsTo(WorkOrderLine::class); }
    public function rectifiesLine(): BelongsTo { return $this->belongsTo(self::class, 'rectifies_line_id'); }
}
