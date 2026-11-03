<?php

namespace App\Models;

use App\Support\FiscalValidator;
use Database\Factories\TaxRateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name', 'kind', 'percentage', 'valid_from', 'valid_until', 'legal_basis', 'requires_evidence', 'is_active'])]
class TaxRate extends Model
{
    /** @use HasFactory<TaxRateFactory> */
    use HasFactory;

    protected $attributes = [
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
            'percentage' => 'decimal:4',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'requires_evidence' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
