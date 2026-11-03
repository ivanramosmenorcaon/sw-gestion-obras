<?php

namespace App\Models;

use App\Support\BusinessRecordValidator;
use Database\Factories\PriceListFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['code', 'name', 'currency_code', 'valid_from', 'valid_until', 'is_active'])]
class PriceList extends Model
{
    /** @use HasFactory<PriceListFactory> */
    use HasFactory;

    protected $table = 'price_lists';

    protected $attributes = [
        'currency_code' => 'EUR',
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
            'valid_from' => 'date',
            'valid_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function priceListItems(): HasMany
    {
        return $this->hasMany(PriceListItem::class, 'price_list_id');
    }

    public function customerSettings(): HasMany
    {
        return $this->hasMany(PartyCustomerSetting::class, 'price_list_id');
    }
}
