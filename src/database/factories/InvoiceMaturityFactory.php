<?php

namespace Database\Factories;

use App\Models\InvoiceMaturity;
use App\Models\PurchaseInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InvoiceMaturity> */
class InvoiceMaturityFactory extends Factory
{
    public function definition(): array
    {
        return ['purchase_invoice_id' => PurchaseInvoice::factory()->confirmed(),
            'project_id' => fn (array $a) => PurchaseInvoice::findOrFail($a['purchase_invoice_id'])->project_id, 'reference' => fake()->unique()->bothify('OP-########'), 'description' => 'Operación', 'amount' => '100', 'operation_date' => today()];
    }
}
