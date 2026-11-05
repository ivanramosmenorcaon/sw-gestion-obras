<?php

namespace Database\Factories;

use App\Models\Party;
use App\Models\Project;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PurchaseOrder> */
class PurchaseOrderFactory extends Factory
{
    public function definition(): array
    {
        return ['project_id' => Project::factory(), 'supplier_id' => Party::factory()->supplier(), 'amount' => '1000.00', 'operation_date' => today(), 'reference' => fake()->unique()->bothify('OP-########'), 'description' => 'Operación de prueba'];
    }
}
