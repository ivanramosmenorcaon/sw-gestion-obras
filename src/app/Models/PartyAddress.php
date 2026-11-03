<?php

namespace App\Models;

use App\Support\BusinessRecordValidator;
use Database\Factories\PartyAddressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable(['party_id', 'label', 'address_line_1', 'address_line_2', 'city', 'province', 'postal_code', 'country_code', 'is_fiscal', 'is_delivery', 'is_active'])]
class PartyAddress extends Model
{
    /** @use HasFactory<PartyAddressFactory> */
    use HasFactory;

    protected $table = 'party_addresses';

    protected $attributes = [
        'country_code' => 'ES',
        'is_fiscal' => false,
        'is_delivery' => false,
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
            'is_fiscal' => 'boolean',
            'is_delivery' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }
}
