<?php

namespace Database\Factories;

use App\Models\Unit;
use App\Models\UnitConversion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UnitConversion> */
class UnitConversionFactory extends Factory
{
    protected $model = UnitConversion::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'from_unit_id' => Unit::factory(),
            'to_unit_id' => Unit::factory(),
            'factor' => '1',
        ];
    }
}
