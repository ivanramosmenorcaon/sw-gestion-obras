<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeAbsence;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EmployeeAbsence> */
class EmployeeAbsenceFactory extends Factory
{
    public function definition(): array
    {
        return ['employee_id' => Employee::factory(), 'starts_on' => today(), 'ends_on' => today(), 'reference' => fake()->unique()->bothify('OP-########'), 'description' => 'Operación de prueba'];
    }
}
