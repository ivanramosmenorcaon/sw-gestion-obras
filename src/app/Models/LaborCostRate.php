<?php

namespace App\Models;

use App\Support\ProjectCosting;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['employee_id', 'contractor_id', 'hourly_cost', 'currency_code', 'valid_from', 'valid_until', 'notes'])]
class LaborCostRate extends Model
{
    use HasFactory;

    protected $attributes = ['currency_code' => 'EUR'];

    protected function casts(): array
    {
        return ['hourly_cost' => 'decimal:6', 'valid_from' => 'date', 'valid_until' => 'date'];
    }

    protected static function booted(): void
    {
        static::saving(fn (self $record) => app(ProjectCosting::class)->validate($record));
        static::deleting(function (self $record): void {
            app(ProjectCosting::class)->assertDeletable($record);
        });
    }

    public function save(array $options = []): bool
    {
        return $this->getConnection()->transaction(function () use ($options): bool {
            if ($this->exists) {
                $stored = $this->newQuery()->whereKey($this->id)->lockForUpdate()->firstOrFail();
                app(ProjectCosting::class)->assertEditable($stored);
            }
            app(ProjectCosting::class)->lockContext($this);

            return parent::save($options);
        }, 3);
    }

    public function delete(): ?bool
    {
        return $this->getConnection()->transaction(function (): ?bool {
            $stored = $this->newQuery()->whereKey($this->id)->lockForUpdate()->firstOrFail();
            app(ProjectCosting::class)->assertDeletable($stored);
            app(ProjectCosting::class)->lockContext($this);

            return parent::delete();
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'contractor_id');
    }

    public function workTimeEntries(): HasMany
    {
        return $this->hasMany(WorkTimeEntry::class, 'labor_cost_rate_id');
    }
}
