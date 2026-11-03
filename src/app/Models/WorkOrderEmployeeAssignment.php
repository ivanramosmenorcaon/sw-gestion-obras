<?php

namespace App\Models;

use App\Support\BusinessRecordValidator;
use Database\Factories\WorkOrderEmployeeAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable(['work_order_id', 'work_order_phase_id', 'employee_id', 'role', 'status', 'planned_start_date', 'planned_end_date', 'planned_hours', 'notes'])]
class WorkOrderEmployeeAssignment extends Model
{
    /** @use HasFactory<WorkOrderEmployeeAssignmentFactory> */
    use HasFactory;

    protected $table = 'work_order_employee_assignments';

    protected $attributes = [
        'status' => 'planned',
        'planned_hours' => '0',
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
            'planned_start_date' => 'date',
            'planned_end_date' => 'date',
            'planned_hours' => 'decimal:6',
        ];
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
}
