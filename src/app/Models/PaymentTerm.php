<?php

namespace App\Models;

use App\Support\BusinessRecordValidator;
use Database\Factories\PaymentTermFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['code', 'name', 'is_active'])]
class PaymentTerm extends Model
{
    /** @use HasFactory<PaymentTermFactory> */
    use HasFactory;

    protected $table = 'payment_terms';

    protected $attributes = [
        'is_active' => true,
    ];

    protected static function booted(): void
    {
        static::saving(function (self $record): void {
            BusinessRecordValidator::validate($record);
        });
        static::deleting(function (self $record): void {
            if (! BusinessRecordValidator::canDelete($record)) {
                throw ValidationException::withMessages(['id' => __('business.messages.referenced')]);
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function paymentTermInstallments(): HasMany
    {
        return $this->hasMany(PaymentTermInstallment::class, 'payment_term_id');
    }

    public function customerSettings(): HasMany
    {
        return $this->hasMany(PartyCustomerSetting::class, 'payment_term_id');
    }

    public function supplierSettings(): HasMany
    {
        return $this->hasMany(PartySupplierSetting::class, 'payment_term_id');
    }
}
