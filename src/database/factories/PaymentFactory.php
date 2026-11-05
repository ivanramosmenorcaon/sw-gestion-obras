<?php

namespace Database\Factories;

use App\Models\Party;
use App\Models\Payment;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return ['project_id' => Project::factory(), 'party_id' => Party::factory()->customer(), 'direction' => 'receipt', 'amount' => '100.00', 'method' => 'transfer', 'operation_date' => today(), 'reference' => fake()->unique()->bothify('OP-########'), 'description' => 'Operación de prueba'];
    }
}
