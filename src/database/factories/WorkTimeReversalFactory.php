<?php

namespace Database\Factories;

use App\Models\LaborCostRate;
use App\Models\WorkOrderEmployeeAssignment;
use App\Models\WorkTimeEntry;
use App\Models\WorkTimeReversal;
use App\Support\ProjectCosting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkTimeReversal> */
class WorkTimeReversalFactory extends Factory
{
    public function definition(): array
    {
        return ['work_time_entry_id' => function (): int {
            $entry = WorkTimeEntry::factory()->create();
            WorkOrderEmployeeAssignment::factory()->create(['employee_id' => $entry->employee_id, 'work_order_id' => $entry->work_order_id]);
            LaborCostRate::factory()->create(['employee_id' => $entry->employee_id]);

            return app(ProjectCosting::class)->postTime($entry)->id;
        }, 'operation_date' => today(), 'reference' => fake()->unique()->bothify('OP-########'), 'description' => 'Operación de prueba'];
    }
}
