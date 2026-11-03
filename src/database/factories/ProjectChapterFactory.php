<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectChapter;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProjectChapter> */
class ProjectChapterFactory extends Factory
{
    protected $model = ProjectChapter::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'code' => fake()->unique()->bothify('REF-########'),
            'name' => fake()->words(3, true),
        ];
    }
}
