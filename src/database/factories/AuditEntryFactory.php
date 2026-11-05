<?php

namespace Database\Factories;

use App\Models\AuditEntry;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditEntry>
 */
class AuditEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event' => 'updated', 'subject_type' => Project::class,
            'subject_id' => Project::factory(),
            'changed_fields' => ['name'],
        ];
    }
}
