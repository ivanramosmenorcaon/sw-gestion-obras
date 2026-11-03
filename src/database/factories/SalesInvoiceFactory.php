<?php

namespace Database\Factories;

use App\Models\InvoiceSeries;
use App\Models\Party;
use App\Models\SalesInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SalesInvoice> */
class SalesInvoiceFactory extends Factory
{
    public function definition(): array
    {
        return ['customer_id' => Party::factory()->customer(), 'invoice_series_id' => InvoiceSeries::factory(), 'description' => 'Trabajos de instalación', 'issue_date' => today('Europe/Madrid')->toDateString(), 'operation_date' => today('Europe/Madrid')->toDateString()];
    }
}
