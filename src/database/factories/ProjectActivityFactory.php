<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProjectActivity> */
class ProjectActivityFactory extends Factory
{
    public function definition(): array
    {
        return ['project_id' => Project::factory(), 'operation_date' => today(), 'category' => 'incident', 'reference' => fake()->unique()->bothify('OP-########'), 'description' => 'Operación de prueba'];
    }
}
