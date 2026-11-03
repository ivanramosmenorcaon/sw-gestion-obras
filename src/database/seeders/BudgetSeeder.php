<?php

namespace Database\Seeders;

use App\Models\Budget;
use Illuminate\Database\Seeder;

class BudgetSeeder extends Seeder
{
    public function run(): void
    {
        if (! Budget::query()->where('reference', 'DEMO-PRE-001')->exists()) {
            Budget::factory()->create(['reference' => 'DEMO-PRE-001', 'name' => 'Presupuesto de demostración']);
        }
    }
}
