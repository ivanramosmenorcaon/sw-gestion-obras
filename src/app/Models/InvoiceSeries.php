<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class InvoiceSeries extends Model
{
    use HasFactory;

    protected $table = 'invoice_series';
    protected $fillable = ['code', 'name', 'fiscal_year', 'kind', 'environment', 'is_active'];
    protected $attributes = ['kind' => 'standard', 'environment' => 'test', 'is_active' => true, 'next_number' => 1];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'next_number' => 'integer', 'fiscal_year' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $series): void {
            Validator::make($series->getAttributes(), [
                'code' => ['required', 'regex:/^[A-Z0-9-]{1,15}$/'], 'name' => ['required', 'string', 'max:100'],
                'fiscal_year' => ['required', 'integer', 'between:2026,2100'], 'kind' => ['required', 'in:standard,rectifying'],
                'environment' => ['required', 'in:test,production'], 'next_number' => ['required', 'integer', 'min:1'],
            ])->validate();
            if ($series->exists && $series->invoices()->where('status', 'issued')->exists() && $series->isDirty(['code', 'fiscal_year', 'kind', 'environment'])) {
                throw ValidationException::withMessages(['code' => __('billing.messages.used_series')]);
            }
        });
        static::deleting(fn () => throw ValidationException::withMessages(['id' => __('billing.messages.no_delete')]));
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SalesInvoice::class);
    }
}
