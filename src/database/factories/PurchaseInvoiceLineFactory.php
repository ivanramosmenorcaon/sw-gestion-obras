<?php

namespace Database\Factories;

use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PurchaseInvoiceLine> */
class PurchaseInvoiceLineFactory extends Factory
{
    public function definition(): array
    {
        return ['purchase_invoice_id' => PurchaseInvoice::factory(), 'description' => 'Material de compra', 'unit_label' => 'ud', 'quantity' => '2', 'unit_price' => '100'];
    }
}
