<?php

namespace Database\Factories;

use App\Models\Party;
use App\Models\Product;
use App\Models\SupplierProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SupplierProduct> */
class SupplierProductFactory extends Factory
{
    protected $model = SupplierProduct::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'supplier_id' => Party::factory()->supplier(),
            'product_id' => Product::factory(),
            'product_unit_id' => fn (array $attributes): int => Product::findOrFail($attributes['product_id'])->productUnits()->firstOrFail()->id,
            'supplier_reference' => fake()->unique()->bothify('REF-########'),
        ];
    }
}
