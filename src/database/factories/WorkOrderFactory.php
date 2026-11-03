<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkOrder> */
class WorkOrderFactory extends Factory
{
    protected $model = WorkOrder::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'reference' => fake()->unique()->bothify('REF-########'),
            'name' => fake()->words(3, true),
        ];
    }
}
