<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\WorkOrderPhase;
use App\Models\WorkTimeEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkTimeEntry> */
class WorkTimeEntryFactory extends Factory
{
    public function definition(): array
    {
        return ['employee_id' => Employee::factory(), 'work_order_phase_id' => WorkOrderPhase::factory(), 'work_order_id' => fn (array $attributes): int => WorkOrderPhase::findOrFail($attributes['work_order_phase_id'])->work_order_id, 'started_at' => now()->subHours(2)->format('Y-m-d H:i:s'), 'ended_at' => now()->subHour()->format('Y-m-d H:i:s'), 'description' => 'Trabajo de instalación'];
    }
}
