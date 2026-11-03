<?php

namespace Database\Factories;

use App\Models\Unit;
use App\Models\WorkOrder;
use App\Models\WorkOrderLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkOrderLine> */
class WorkOrderLineFactory extends Factory
{
    protected $model = WorkOrderLine::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'work_order_id' => WorkOrder::factory(),
            'project_id' => fn (array $attributes): int => WorkOrder::findOrFail($attributes['work_order_id'])->project_id,
            'unit_id' => Unit::factory(),
            'base_unit_id' => fn (array $attributes): int => $attributes['unit_id'],
            'position' => 1,
            'type' => 'material',
            'description' => 'Ejemplo',
            'unit_label' => 'Ejemplo',
        ];
    }
}
