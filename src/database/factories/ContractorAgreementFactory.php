<?php

namespace Database\Factories;

use App\Models\ContractorAgreement;
use App\Models\Party;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContractorAgreement> */
class ContractorAgreementFactory extends Factory
{
    public function definition(): array
    {
        return ['project_id' => Project::factory(), 'contractor_id' => Party::factory()->contractor(), 'amount' => '10000.00', 'retention_percentage' => '5', 'reference' => fake()->unique()->bothify('OP-########'), 'description' => 'Operación de prueba'];
    }

    public function confirmed(): static
    {
        return $this->afterCreating(function (ContractorAgreement $record): void {
            $record->forceFill(['status' => 'posted'])->save();
        });
    }
}
