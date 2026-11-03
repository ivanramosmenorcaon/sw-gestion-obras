<?php

namespace Database\Factories;

use App\Models\InvoiceSeries;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InvoiceSeries> */
class InvoiceSeriesFactory extends Factory
{
    public function definition(): array
    {
        return ['code' => fake()->unique()->bothify('F-#####'), 'name' => 'Serie de pruebas', 'fiscal_year' => today('Europe/Madrid')->year, 'kind' => 'standard', 'environment' => 'test'];
    }
    public function rectifying(): static { return $this->state(fn (): array => ['kind' => 'rectifying', 'code' => fake()->unique()->bothify('R-#####')]); }
}
