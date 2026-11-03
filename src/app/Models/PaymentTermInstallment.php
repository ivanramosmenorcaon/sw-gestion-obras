<?php

namespace App\Models;

use App\Support\BusinessRecordValidator;
use Database\Factories\PaymentTermInstallmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable(['payment_term_id', 'sequence', 'percentage', 'days_after_reference', 'reference_event'])]
class PaymentTermInstallment extends Model
{
    /** @use HasFactory<PaymentTermInstallmentFactory> */
    use HasFactory;

    protected $table = 'payment_term_installments';

    protected $attributes = [
        'days_after_reference' => '0',
        'reference_event' => 'invoice_date',
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
            'percentage' => 'decimal:4',
        ];
    }

    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class, 'payment_term_id');
    }
}
