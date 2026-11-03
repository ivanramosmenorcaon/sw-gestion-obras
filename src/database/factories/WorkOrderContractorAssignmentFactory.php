<?php

namespace Database\Factories;

use App\Models\Party;
use App\Models\WorkOrder;
use App\Models\WorkOrderContractorAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkOrderContractorAssignment> */
class WorkOrderContractorAssignmentFactory extends Factory
{
    protected $model = WorkOrderContractorAssignment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'work_order_id' => WorkOrder::factory(),
            'contractor_id' => Party::factory()->contractor(),
            'scope' => 'Ejemplo',
        ];
    }
}
