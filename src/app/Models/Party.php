<?php

namespace App\Models;

use App\Support\BusinessRecordValidator;
use Database\Factories\PartyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

#[Fillable(['type', 'legal_name', 'trade_name', 'tax_country_code', 'tax_identification_type', 'tax_identification_number', 'locale', 'notes', 'is_active', 'sales_tax_profile_id', 'purchase_tax_profile_id', 'sales_withholding_rate_id', 'purchase_withholding_rate_id', 'fiscal_notes', 'taxpayer_capacity', 'tax_territory', 'iae_code', 'withholding_obliged', 'equivalence_surcharge_applicable'])]
class Party extends Model
{
    /** @use HasFactory<PartyFactory> */
    use HasFactory;

    protected $table = 'parties';

    protected $attributes = [
        'tax_country_code' => 'ES',
        'tax_identification_type' => 'nif',
        'locale' => 'es',
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
            'withholding_obliged' => 'boolean',
            'equivalence_surcharge_applicable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'customer_id');
    }

    public function workOrderContractorAssignments(): HasMany
    {
        return $this->hasMany(WorkOrderContractorAssignment::class, 'contractor_id');
    }

    public function partyRoles(): HasMany
    {
        return $this->hasMany(PartyRole::class, 'party_id');
    }

    public function partyAddresses(): HasMany
    {
        return $this->hasMany(PartyAddress::class, 'party_id');
    }

    public function partyContacts(): HasMany
    {
        return $this->hasMany(PartyContact::class, 'party_id');
    }

    public function supplierProducts(): HasMany
    {
        return $this->hasMany(SupplierProduct::class, 'supplier_id');
    }

    public function customerSettings(): HasOne
    {
        return $this->hasOne(PartyCustomerSetting::class, 'party_id');
    }

    public function supplierSettings(): HasOne
    {
        return $this->hasOne(PartySupplierSetting::class, 'party_id');
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
