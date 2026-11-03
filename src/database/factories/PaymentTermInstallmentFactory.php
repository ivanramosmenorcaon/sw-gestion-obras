<?php

namespace Database\Factories;

use App\Models\PaymentTerm;
use App\Models\PaymentTermInstallment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PaymentTermInstallment> */
class PaymentTermInstallmentFactory extends Factory
{
    protected $model = PaymentTermInstallment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'payment_term_id' => PaymentTerm::factory(),
            'sequence' => 1,
            'percentage' => '1',
        ];
    }
}
