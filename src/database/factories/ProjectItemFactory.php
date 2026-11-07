<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectItem;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectItem>
 */
class ProjectItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(), 'position' => 1, 'description' => 'Colocación de pavimento', 'type' => 'service',
            'unit_id' => Unit::factory(), 'base_unit_id' => fn (array $attributes): int => $attributes['unit_id'],
            'unit_label' => 'm²', 'planned_quantity' => '80', 'estimated_unit_sale_price' => '40',
        ];
    }
}
