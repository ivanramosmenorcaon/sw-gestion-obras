<?php

namespace Database\Factories;

use App\Models\TaxRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TaxRate> */
class TaxRateFactory extends Factory
{
    protected $model = TaxRate::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['code' => fake()->unique()->bothify('TAX-########'), 'name' => 'IVA general', 'kind' => 'vat', 'percentage' => '21', 'valid_from' => '2026-01-01'];
    }
}
