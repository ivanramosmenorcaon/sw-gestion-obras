<?php

namespace Database\Factories;

use App\Models\TaxProfile;
use App\Models\TaxRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TaxProfile> */
class TaxProfileFactory extends Factory
{
    protected $model = TaxProfile::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['code' => fake()->unique()->bothify('PROFILE-########'), 'name' => 'IVA general', 'tax_system' => '01', 'regime_code' => '01', 'operation_class' => 'S1', 'tax_rate_id' => TaxRate::factory()];
    }
}
