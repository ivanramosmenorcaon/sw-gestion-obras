<?php

namespace Database\Factories;

use App\Models\WorkOrder;
use App\Models\WorkOrderPhase;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkOrderPhase> */
class WorkOrderPhaseFactory extends Factory
{
    protected $model = WorkOrderPhase::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'work_order_id' => WorkOrder::factory(),
            'code' => fake()->unique()->bothify('REF-########'),
            'name' => fake()->words(3, true),
        ];
    }
}
