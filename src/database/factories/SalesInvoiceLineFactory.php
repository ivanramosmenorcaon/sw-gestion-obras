<?php

namespace Database\Factories;

use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLine;
use App\Models\TaxProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SalesInvoiceLine> */
class SalesInvoiceLineFactory extends Factory
{
    public function definition(): array
    {
        return ['sales_invoice_id' => SalesInvoice::factory(), 'description' => 'Instalación', 'quantity' => '1', 'unit_price' => '100', 'tax_profile_id' => TaxProfile::factory()];
    }
}
