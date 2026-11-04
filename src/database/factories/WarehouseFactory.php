<?php

namespace Database\Factories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Warehouse> */
class WarehouseFactory extends Factory
{
    public function definition(): array
    {
        return ['code' => fake()->unique()->bothify('ALM-#####'), 'name' => 'Almacén de pruebas'];
    }
}
