<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductUnit> */
class ProductUnitFactory extends Factory
{
    protected $model = ProductUnit::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'unit_id' => fn (array $attributes): int => Product::findOrFail($attributes['product_id'])->base_unit_id,
            'code' => fake()->unique()->bothify('REF-########'),
            'name' => fake()->words(3, true),
            'base_unit_factor' => '1',
        ];
    }
}
