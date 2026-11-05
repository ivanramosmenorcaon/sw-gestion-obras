<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\PurchaseInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PaymentApplication> */
class PaymentApplicationFactory extends Factory
{
    public function definition(): array
    {
        return ['purchase_invoice_id' => PurchaseInvoice::factory()->confirmed(),
            'project_id' => fn (array $a) => PurchaseInvoice::findOrFail($a['purchase_invoice_id'])->project_id, 'payment_id' => function (array $a): int {
                $invoice = PurchaseInvoice::findOrFail($a['purchase_invoice_id']);
                $payment = Payment::factory()->create(['project_id' => $invoice->project_id, 'party_id' => $invoice->supplier_id, 'direction' => 'payment']);
                $payment->forceFill(['status' => 'posted'])->save();

                return $payment->id;
            },
            'reference' => fake()->unique()->bothify('OP-########'), 'description' => 'Operación', 'amount' => '100', 'operation_date' => today()];
    }
}
