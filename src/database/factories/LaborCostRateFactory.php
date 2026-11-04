<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\LaborCostRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LaborCostRate> */
class LaborCostRateFactory extends Factory
{
    public function definition(): array
    {
        return ['employee_id' => Employee::factory(), 'hourly_cost' => '20', 'valid_from' => '2026-01-01'];
    }
}
