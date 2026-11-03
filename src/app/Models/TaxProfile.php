<?php

namespace App\Models;

use App\Support\FiscalValidator;
use Database\Factories\TaxProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['code', 'name', 'tax_system', 'regime_code', 'operation_class', 'exemption_code', 'tax_rate_id', 'surcharge_rate_id', 'legal_basis', 'requires_evidence', 'is_active'])]
class TaxProfile extends Model
{
    /** @use HasFactory<TaxProfileFactory> */
    use HasFactory;

    protected $attributes = [
        'tax_system' => '01',
        'requires_evidence' => false,
        'is_active' => true,
    ];

    protected static function booted(): void
    {
        static::saving(function (self $record): void {
            FiscalValidator::validate($record);
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'requires_evidence' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class, 'tax_rate_id');
    }

    public function surchargeRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class, 'surcharge_rate_id');
    }
}
