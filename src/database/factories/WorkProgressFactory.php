<?php

namespace Database\Factories;

use App\Models\WorkOrderLine;
use App\Models\WorkProgress;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkProgress> */
class WorkProgressFactory extends Factory
{
    public function definition(): array
    {
        return ['work_order_line_id' => WorkOrderLine::factory(), 'project_id' => fn (array $a) => WorkOrderLine::findOrFail($a['work_order_line_id'])->project_id, 'quantity' => '1', 'operation_date' => today(), 'reference' => fake()->unique()->bothify('OP-########'), 'description' => 'Operación de prueba'];
    }
}
