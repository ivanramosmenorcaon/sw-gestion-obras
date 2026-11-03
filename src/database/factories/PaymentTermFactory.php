<?php

namespace Database\Factories;

use App\Models\PaymentTerm;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PaymentTerm> */
class PaymentTermFactory extends Factory
{
    protected $model = PaymentTerm::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('REF-########'),
            'name' => fake()->words(3, true),
        ];
    }
}
