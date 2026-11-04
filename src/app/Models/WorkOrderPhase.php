<?php

namespace App\Models;

use App\Support\BusinessRecordValidator;
use Database\Factories\WorkOrderPhaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['work_order_id', 'code', 'name', 'description', 'position', 'status', 'planned_start_date', 'planned_end_date', 'actual_start_date', 'actual_end_date'])]
class WorkOrderPhase extends Model
{
    /** @use HasFactory<WorkOrderPhaseFactory> */
    use HasFactory;

    protected $table = 'work_order_phases';

    protected $attributes = [
        'position' => '1',
        'status' => 'pending',
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
            $owners = array_filter([$this->work_order_id, $this->getRawOriginal('work_order_id')]);
            WorkOrder::whereKey(array_unique($owners))->orderBy('id')->lockForUpdate()->get();

            $this->unsetRelation('workOrder');

            return parent::save($options);
        });
    }

    public function delete(): ?bool
    {
        return $this->getConnection()->transaction(function (): ?bool {
            WorkOrder::whereKey($this->work_order_id)->lockForUpdate()->firstOrFail();

            $this->unsetRelation('workOrder');

            return parent::delete();
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

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'work_order_id');
    }

    public function workOrderLines(): HasMany
    {
        return $this->hasMany(WorkOrderLine::class, 'work_order_phase_id');
    }

    public function workOrderEmployeeAssignments(): HasMany
    {
        return $this->hasMany(WorkOrderEmployeeAssignment::class, 'work_order_phase_id');
    }

    public function workOrderContractorAssignments(): HasMany
    {
        return $this->hasMany(WorkOrderContractorAssignment::class, 'work_order_phase_id');
    }

    public function materialConsumptions(): HasMany
    {
        return $this->hasMany(MaterialConsumption::class, 'work_order_phase_id');
    }

    public function workTimeEntries(): HasMany
    {
        return $this->hasMany(WorkTimeEntry::class, 'work_order_phase_id');
    }
}
