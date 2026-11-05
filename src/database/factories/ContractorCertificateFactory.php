<?php

namespace Database\Factories;

use App\Models\ContractorAgreement;
use App\Models\ContractorCertificate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContractorCertificate> */
class ContractorCertificateFactory extends Factory
{
    public function definition(): array
    {
        return ['contractor_agreement_id' => ContractorAgreement::factory()->confirmed(), 'project_id' => fn (array $a) => ContractorAgreement::findOrFail($a['contractor_agreement_id'])->project_id, 'amount' => '1000.00', 'operation_date' => today(), 'reference' => fake()->unique()->bothify('OP-########'), 'description' => 'Operación de prueba'];
    }
}
