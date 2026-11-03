<?php

namespace Database\Factories;

use App\Models\PriceList;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PriceList> */
class PriceListFactory extends Factory
{
    protected $model = PriceList::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('REF-########'),
            'name' => fake()->words(3, true),
        ];
    }
}
