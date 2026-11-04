<?php

namespace App\Models;

use App\Support\BusinessRecordValidator;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['code', 'name', 'user_id', 'email', 'phone', 'job_title', 'employment_start_date', 'employment_end_date', 'is_active'])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    protected $table = 'employees';

    protected $attributes = [
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
            'employment_start_date' => 'date',
            'employment_end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class, 'supervisor_id');
    }

    public function workOrderEmployeeAssignments(): HasMany
    {
        return $this->hasMany(WorkOrderEmployeeAssignment::class, 'employee_id');
    }

    public function laborCostRates(): HasMany
    {
        return $this->hasMany(LaborCostRate::class, 'employee_id');
    }

    public function workTimeEntries(): HasMany
    {
        return $this->hasMany(WorkTimeEntry::class, 'employee_id');
    }
}
