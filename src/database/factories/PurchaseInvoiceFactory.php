<?php

namespace Database\Factories;

use App\Models\Party;
use App\Models\Project;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PurchaseInvoice> */
class PurchaseInvoiceFactory extends Factory
{
    public function definition(): array
    {
        return ['project_id' => Project::factory(), 'supplier_id' => Party::factory()->supplier(), 'operation_date' => today(), 'due_date' => today()->addDays(30), 'net_amount' => '100.00', 'reference' => fake()->unique()->bothify('OP-########'), 'description' => 'Operación de prueba'];
    }

    public function confirmed(): static
    {
        return $this->afterCreating(function (PurchaseInvoice $invoice): void {
            PurchaseInvoiceLine::factory()->create(['purchase_invoice_id' => $invoice->id, 'quantity' => '1', 'unit_price' => $invoice->net_amount]);
            $invoice->refresh()->forceFill(['status' => 'posted'])->save();
        });
    }
}
