<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectExpense;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProjectExpense> */
class ProjectExpenseFactory extends Factory
{
    public function definition(): array
    {
        return ['project_id' => Project::factory(), 'operation_date' => today(), 'category' => 'indirect', 'amount' => '100.00', 'reference' => fake()->unique()->bothify('OP-########'), 'description' => 'Operación de prueba'];
    }
}
