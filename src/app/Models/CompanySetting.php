<?php

namespace App\Models;

use App\Support\BusinessRecordValidator;
use Database\Factories\CompanySettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

#[Fillable(['scope', 'legal_name', 'trade_name', 'tax_identification_type', 'tax_identification_number', 'address_line_1', 'address_line_2', 'city', 'province', 'postal_code', 'country_code', 'currency_code', 'timezone', 'email', 'phone'])]
class CompanySetting extends Model
{
    /** @use HasFactory<CompanySettingFactory> */
    use HasFactory;

    protected $table = 'company_settings';

    protected $attributes = [
        'scope' => 'company',
        'tax_identification_type' => 'nif',
        'country_code' => 'ES',
        'currency_code' => 'EUR',
        'timezone' => 'Europe/Madrid',
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
}
