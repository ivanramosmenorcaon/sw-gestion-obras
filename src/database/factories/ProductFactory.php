<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'base_unit_id' => Unit::factory(),
            'reference' => fake()->unique()->bothify('REF-########'),
            'name' => fake()->words(3, true),
            'type' => 'material',
        ];
    }
}
