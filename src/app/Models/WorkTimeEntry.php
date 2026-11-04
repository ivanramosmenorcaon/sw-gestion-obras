<?php

namespace App\Models;

use App\Support\ProjectCosting;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['work_order_id', 'work_order_phase_id', 'employee_id', 'contractor_id', 'started_at', 'ended_at', 'break_minutes', 'description'])]
class WorkTimeEntry extends Model
{
    use HasFactory;

    protected $attributes = ['status' => 'draft', 'break_minutes' => 0];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime', 'break_minutes' => 'integer', 'hours' => 'decimal:6', 'hourly_cost' => 'decimal:6', 'total_cost' => 'decimal:2', 'posted_at' => 'datetime'];
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

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'work_order_id');
    }

    public function workOrderPhase(): BelongsTo
    {
        return $this->belongsTo(WorkOrderPhase::class, 'work_order_phase_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'contractor_id');
    }

    public function laborCostRate(): BelongsTo
    {
        return $this->belongsTo(LaborCostRate::class, 'labor_cost_rate_id');
    }
}
