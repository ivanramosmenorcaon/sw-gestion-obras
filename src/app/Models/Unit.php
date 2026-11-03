<?php

namespace App\Models;

use App\Support\BusinessRecordValidator;
use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['code', 'name', 'symbol', 'magnitude', 'display_precision', 'is_active'])]
class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use HasFactory;

    protected $table = 'units';

    protected $attributes = [
        'display_precision' => '3',
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

    public function workOrderLines(): HasMany
    {
        return $this->hasMany(WorkOrderLine::class, 'unit_id');
    }

    public function workOrderLinesByBaseUnit(): HasMany
    {
        return $this->hasMany(WorkOrderLine::class, 'base_unit_id');
    }

    public function unitConversionsByFromUnit(): HasMany
    {
        return $this->hasMany(UnitConversion::class, 'from_unit_id');
    }

    public function unitConversionsByToUnit(): HasMany
    {
        return $this->hasMany(UnitConversion::class, 'to_unit_id');
    }

    public function productsByBaseUnit(): HasMany
    {
        return $this->hasMany(Product::class, 'base_unit_id');
    }

    public function productUnits(): HasMany
    {
        return $this->hasMany(ProductUnit::class, 'unit_id');
    }
}
