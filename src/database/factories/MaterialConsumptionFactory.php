<?php

namespace Database\Factories;

use App\Models\MaterialConsumption;
use App\Models\PurchaseDeliveryLine;
use App\Models\WorkOrderPhase;
use App\Support\ProjectCosting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MaterialConsumption> */
class MaterialConsumptionFactory extends Factory
{
    public function definition(): array
    {
        return ['purchase_delivery_line_id' => PurchaseDeliveryLine::factory()->afterCreating(fn (PurchaseDeliveryLine $line) => app(ProjectCosting::class)->receive($line->deliveryNote)), 'work_order_phase_id' => WorkOrderPhase::factory(), 'work_order_id' => fn (array $attributes): int => WorkOrderPhase::findOrFail($attributes['work_order_phase_id'])->work_order_id, 'consumed_date' => today('Europe/Madrid')->toDateString(), 'quantity' => '1'];
    }
}
