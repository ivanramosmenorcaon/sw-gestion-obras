<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\PurchaseDeliveryLine;
use App\Models\PurchaseDeliveryNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PurchaseDeliveryLine> */
class PurchaseDeliveryLineFactory extends Factory
{
    public function definition(): array
    {
        return ['purchase_delivery_note_id' => PurchaseDeliveryNote::factory(), 'product_id' => Product::factory()->state(['tracks_stock' => true]), 'description' => 'Material recibido', 'quantity' => '10', 'unit_price' => '100'];
    }
}
