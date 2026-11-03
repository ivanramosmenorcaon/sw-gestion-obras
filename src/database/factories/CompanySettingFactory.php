<?php

namespace Database\Factories;

use App\Models\CompanySetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CompanySetting> */
class CompanySettingFactory extends Factory
{
    protected $model = CompanySetting::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'legal_name' => fake()->company(),
            'tax_identification_number' => fake()->unique()->numerify('B########'),
            'address_line_1' => fake()->streetAddress(),
            'city' => fake()->city(),
        ];
    }
}
