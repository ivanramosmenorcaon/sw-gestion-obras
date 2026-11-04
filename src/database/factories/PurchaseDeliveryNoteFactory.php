<?php

namespace Database\Factories;

use App\Models\Party;
use App\Models\PurchaseDeliveryNote;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PurchaseDeliveryNote> */
class PurchaseDeliveryNoteFactory extends Factory
{
    public function definition(): array
    {
        return ['supplier_id' => Party::factory()->supplier(), 'warehouse_id' => Warehouse::factory(), 'reference' => fake()->unique()->bothify('ALB-#####'), 'received_date' => today('Europe/Madrid')->toDateString()];
    }
}
