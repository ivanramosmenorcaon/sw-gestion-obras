<?php

namespace App\Models;

use App\Support\BusinessRecordValidator;
use Database\Factories\CompanySettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable(['sales_markup_percentage', 'scope', 'legal_name', 'trade_name', 'tax_identification_type', 'tax_identification_number', 'address_line_1', 'address_line_2', 'city', 'province', 'postal_code', 'country_code', 'currency_code', 'timezone', 'email', 'phone', 'sales_tax_profile_id', 'purchase_tax_profile_id', 'sales_withholding_rate_id', 'purchase_withholding_rate_id', 'fiscal_notes', 'taxpayer_capacity', 'tax_territory', 'iae_code', 'withholding_obliged', 'equivalence_surcharge_applicable'])]
class CompanySetting extends Model
{
    /** @use HasFactory<CompanySettingFactory> */
    use HasFactory;

    protected $table = 'company_settings';

    protected $attributes = [
        'sales_markup_percentage' => '15',
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
            'sales_markup_percentage' => 'decimal:4',
            'withholding_obliged' => 'boolean',
            'equivalence_surcharge_applicable' => 'boolean',
        ];
    }

    public function salesTaxProfile(): BelongsTo
    {
        return $this->belongsTo(TaxProfile::class, 'sales_tax_profile_id');
    }

    public function purchaseTaxProfile(): BelongsTo
    {
        return $this->belongsTo(TaxProfile::class, 'purchase_tax_profile_id');
    }

    public function salesWithholdingRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class, 'sales_withholding_rate_id');
    }

    public function purchaseWithholdingRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class, 'purchase_withholding_rate_id');
    }
}
