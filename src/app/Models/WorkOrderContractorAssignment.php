<?php

namespace App\Models;

use App\Support\BusinessRecordValidator;
use Database\Factories\WorkOrderContractorAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable(['work_order_id', 'work_order_phase_id', 'contractor_id', 'scope', 'status', 'planned_start_date', 'planned_end_date', 'notes'])]
class WorkOrderContractorAssignment extends Model
{
    /** @use HasFactory<WorkOrderContractorAssignmentFactory> */
    use HasFactory;

    protected $table = 'work_order_contractor_assignments';

    protected $attributes = [
        'status' => 'planned',
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

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'contractor_id');
    }
}
