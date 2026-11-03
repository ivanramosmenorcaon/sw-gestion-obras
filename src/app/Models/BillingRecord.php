<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

class BillingRecord extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['system_snapshot' => 'array']; }
    protected static function booted(): void
    {
        static::updating(fn () => throw ValidationException::withMessages(['id' => __('billing.messages.immutable')]));
        static::deleting(fn () => throw ValidationException::withMessages(['id' => __('billing.messages.immutable')]));
    }
    public function invoice(): BelongsTo { return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id'); }
    public function previousRecord(): BelongsTo { return $this->belongsTo(self::class, 'previous_record_id'); }
    public function submission(): HasOne { return $this->hasOne(BillingSubmission::class); }
}
