<?php

namespace App\Models;

use App\Support\SalesBilling;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

class SalesInvoice extends Model
{
    use HasFactory;

    protected $fillable = ['customer_id', 'invoice_series_id', 'work_order_id', 'issue_date', 'operation_date', 'due_date', 'description', 'notes', 'fiscal_evidence'];

    protected $attributes = ['status' => 'draft', 'invoice_type' => 'F1', 'currency_code' => 'EUR'];

    protected function casts(): array
    {
        return ['issue_date' => 'date', 'operation_date' => 'date', 'due_date' => 'date', 'issued_at' => 'datetime', 'issuer_snapshot' => 'array', 'recipient_snapshot' => 'array', 'tax_summary' => 'array', 'net_total' => 'decimal:2', 'tax_total' => 'decimal:2', 'surcharge_total' => 'decimal:2', 'withholding_total' => 'decimal:2', 'gross_total' => 'decimal:2', 'payable_total' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $invoice): void {
            if ($invoice->exists && $invoice->getRawOriginal('status') !== 'draft') {
                throw ValidationException::withMessages(['status' => __('billing.messages.immutable')]);
            }
            app(SalesBilling::class)->validateHeader($invoice);
        });
        static::deleting(fn () => throw ValidationException::withMessages(['id' => __('billing.messages.no_delete')]));
    }

    public function save(array $options = []): bool
    {
        return $this->getConnection()->transaction(function () use ($options): bool {
            if ($this->exists) {
                $stored = $this->newQuery()->whereKey($this->id)->lockForUpdate()->firstOrFail();
                app(SalesBilling::class)->assertDraft($stored);
            }

            return parent::save($options);
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'customer_id');
    }

    public function invoiceSeries(): BelongsTo
    {
        return $this->belongsTo(InvoiceSeries::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function rectifiesInvoice(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rectifies_invoice_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesInvoiceLine::class)->orderBy('position')->orderBy('id');
    }

    public function billingRecord(): HasOne
    {
        return $this->hasOne(BillingRecord::class);
    }
}
