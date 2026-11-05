<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PurchaseOrderLine> */
class PurchaseOrderLineFactory extends Factory
{
    public function definition(): array
    {
        return ['purchase_order_id' => PurchaseOrder::factory(), 'description' => 'Material de compra', 'unit_label' => 'ud', 'quantity' => '2', 'unit_price' => '100'];
    }
}
