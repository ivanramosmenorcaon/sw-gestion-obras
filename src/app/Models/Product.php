<?php

namespace App\Models;

use App\Support\BusinessRecordValidator;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['sales_markup_percentage', 'reference', 'name', 'description', 'product_category_id', 'type', 'base_unit_id', 'tracks_stock', 'is_active', 'sales_tax_profile_id', 'purchase_tax_profile_id', 'sales_withholding_rate_id', 'purchase_withholding_rate_id', 'fiscal_notes'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $table = 'products';

    protected $attributes = [
        'tracks_stock' => false,
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
        static::created(function (self $record): void {
            $record->productUnits()->create([
                'code' => 'base',
                'name' => $record->baseUnit->name,
                'unit_id' => $record->base_unit_id,
                'base_unit_factor' => '1',
            ]);
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sales_markup_percentage' => 'decimal:4',
            'tracks_stock' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function workOrderLines(): HasMany
    {
        return $this->hasMany(WorkOrderLine::class, 'product_id');
    }

    public function productUnits(): HasMany
    {
        return $this->hasMany(ProductUnit::class, 'product_id');
    }

    public function supplierProducts(): HasMany
    {
        return $this->hasMany(SupplierProduct::class, 'product_id');
    }

    public function priceListItems(): HasMany
    {
        return $this->hasMany(PriceListItem::class, 'product_id');
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

    public function salesInvoiceLines(): HasMany
    {
        return $this->hasMany(SalesInvoiceLine::class, 'product_id');
    }

    public function purchaseDeliveryLines(): HasMany
    {
        return $this->hasMany(PurchaseDeliveryLine::class, 'product_id');
    }
}
