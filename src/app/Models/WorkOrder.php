<?php

namespace App\Models;

use App\Support\BusinessRecordValidator;
use Database\Factories\WorkOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['project_id', 'reference', 'name', 'description', 'supervisor_id', 'status', 'priority', 'planned_start_date', 'planned_end_date', 'actual_start_date', 'actual_end_date', 'instructions', 'measurement_import_hash'])]
class WorkOrder extends Model
{
    /** @use HasFactory<WorkOrderFactory> */
    use HasFactory;

    protected $table = 'work_orders';

    protected $attributes = [
        'status' => 'draft',
        'priority' => 'normal',
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

    public function delete(): ?bool
    {
        if (! $this->exists) {
            return parent::delete();
        }

        return $this->getConnection()->transaction(function (): ?bool {
            $persisted = $this->newQuery()->whereKey($this->getKey())->lockForUpdate()->first();
            if ($persisted === null) {
                return false;
            }
            $this->setRawAttributes($persisted->getAttributes(), sync: true);

            return parent::delete();
        });
    }

    protected function performDeleteOnModel(): void
    {
        if ($this->status === 'draft') {
            $this->workOrderLines()->delete();
            $this->workOrderEmployeeAssignments()->delete();
            $this->workOrderContractorAssignments()->delete();
            $this->workOrderPhases()->delete();
        }

        parent::performDeleteOnModel();
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

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
    }

    public function workOrderPhases(): HasMany
    {
        return $this->hasMany(WorkOrderPhase::class, 'work_order_id');
    }

    public function workOrderLines(): HasMany
    {
        return $this->hasMany(WorkOrderLine::class, 'work_order_id');
    }

    public function workOrderEmployeeAssignments(): HasMany
    {
        return $this->hasMany(WorkOrderEmployeeAssignment::class, 'work_order_id');
    }

    public function workOrderContractorAssignments(): HasMany
    {
        return $this->hasMany(WorkOrderContractorAssignment::class, 'work_order_id');
    }
}
