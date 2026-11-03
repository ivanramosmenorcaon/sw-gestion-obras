<?php

namespace App\Models;

use App\Support\BusinessRecordValidator;
use Database\Factories\PartyCustomerSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable(['party_id', 'payment_term_id', 'price_list_id', 'customer_reference'])]
class PartyCustomerSetting extends Model
{
    /** @use HasFactory<PartyCustomerSettingFactory> */
    use HasFactory;

    protected $table = 'party_customer_settings';

    protected $attributes = [
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
        ];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }

    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class, 'payment_term_id');
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class, 'price_list_id');
    }
}
