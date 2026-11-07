<?php

namespace App\Models;

use App\Support\BusinessRecordValidator;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['reference', 'name', 'description', 'customer_id', 'manager_id', 'status', 'currency_code', 'address_line_1', 'address_line_2', 'city', 'province', 'postal_code', 'country_code', 'planned_start_date', 'planned_end_date', 'actual_start_date', 'actual_end_date', 'notes'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    protected $table = 'projects';

    protected $attributes = [
        'status' => 'draft',
        'currency_code' => 'EUR',
        'country_code' => 'ES',
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

    public function save(array $options = []): bool
    {
        return $this->getConnection()->transaction(function () use ($options): bool {
            if ($this->exists) {
                $this->newQuery()->whereKey($this->id)->lockForUpdate()->firstOrFail();
            }

            return parent::save($options);
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'planned_start_date' => 'date',
            'planned_end_date' => 'date',
            'actual_start_date' => 'date',
            'actual_end_date' => 'date',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'customer_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function projectChapters(): HasMany
    {
        return $this->hasMany(ProjectChapter::class, 'project_id');
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class, 'project_id');
    }

    public function projectItems(): HasMany
    {
        return $this->hasMany(ProjectItem::class);
    }

    public function workOrderLines(): HasMany
    {
        return $this->hasMany(WorkOrderLine::class, 'project_id');
    }
}
