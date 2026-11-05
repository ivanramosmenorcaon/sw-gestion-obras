<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProjectDocument> */
class ProjectDocumentFactory extends Factory
{
    public function definition(): array
    {
        return ['project_id' => Project::factory(), 'category' => 'other', 'path' => 'project-documents/test.pdf', 'reference' => fake()->unique()->bothify('OP-########'), 'description' => 'Operación de prueba'];
    }
}
