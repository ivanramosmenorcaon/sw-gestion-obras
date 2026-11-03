<?php

namespace Database\Seeders;

use App\Models\InvoiceSeries;
use Illuminate\Database\Seeder;

class BillingSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['F' => 'standard', 'R' => 'rectifying'] as $code => $kind) {
            InvoiceSeries::firstOrCreate(['code' => $code, 'fiscal_year' => today('Europe/Madrid')->year, 'environment' => 'test'],
                ['name' => $kind === 'standard' ? 'Facturas de pruebas' : 'Rectificativas de pruebas', 'kind' => $kind]);
        }
    }
}
