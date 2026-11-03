<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Budget> */
class BudgetFactory extends Factory
{
    protected $model = Budget::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(), 'reference' => fake()->unique()->bothify('PRE-########'),
            'name' => 'Presupuesto de prueba', 'currency_code' => 'EUR',
            'items' => [['source_line_id' => 1, 'source_code' => 'P1', 'description' => 'Partida de ejemplo', 'unit' => 'ud', 'quantity' => '1.000000', 'price' => '10.000000', 'chapter_path' => [], 'measurement_details' => [], 'budget_breakdown' => []]],
        ];
    }
}
