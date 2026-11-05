<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\PaymentReversal;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PaymentReversal> */
class PaymentReversalFactory extends Factory
{
    public function definition(): array
    {
        return ['payment_id' => function (): int {
            $payment = Payment::factory()->create();
            $payment->forceFill(['status' => 'posted'])->save();

            return $payment->id;
        },
            'project_id' => fn (array $a) => Payment::findOrFail($a['payment_id'])->project_id, 'reference' => fake()->unique()->bothify('OP-########'), 'description' => 'Operación', 'amount' => '100', 'operation_date' => today()];
    }
}
