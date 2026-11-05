<?php

namespace Database\Factories;

use App\Models\PurchaseDeliveryLine;
use App\Models\StockMovement;
use App\Support\ProjectCosting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StockMovement> */
class StockMovementFactory extends Factory
{
    public function definition(): array
    {
        return ['purchase_delivery_line_id' => function (): int {
            $line = PurchaseDeliveryLine::factory()->create();
            app(ProjectCosting::class)->receive($line->deliveryNote);

            return $line->id;
        }, 'reference' => fake()->unique()->bothify('MOV-########'), 'description' => 'Ajuste de inventario', 'category' => 'adjustment', 'quantity' => '1', 'operation_date' => today()];
    }
}
