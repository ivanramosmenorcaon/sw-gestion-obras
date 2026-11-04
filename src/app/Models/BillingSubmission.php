<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingSubmission extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['claimed_at' => 'datetime', 'retry_after' => 'datetime'];
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(BillingRecord::class, 'billing_record_id');
    }

    #[Scope]
    protected function inEnvironment(Builder $query, string $environment): Builder
    {
        return $query->whereHas('record.invoice', fn (Builder $query): Builder => $query->where('environment', $environment));
    }
}
