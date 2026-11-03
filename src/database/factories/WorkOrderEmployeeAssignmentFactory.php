<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\WorkOrder;
use App\Models\WorkOrderEmployeeAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkOrderEmployeeAssignment> */
class WorkOrderEmployeeAssignmentFactory extends Factory
{
    protected $model = WorkOrderEmployeeAssignment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'work_order_id' => WorkOrder::factory(),
            'employee_id' => Employee::factory(),
        ];
    }
}
