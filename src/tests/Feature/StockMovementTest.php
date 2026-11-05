<?php

namespace Tests\Feature;

use App\Models\PurchaseDeliveryLine;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Support\ProjectCosting;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StockMovementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    public function test_transfer_moves_stock_without_changing_its_cost_and_cannot_be_reposted(): void
    {
        $line = PurchaseDeliveryLine::factory()->create(['quantity' => '10', 'unit_price' => '100']);
        app(ProjectCosting::class)->receive($line->deliveryNote);
        $line->refresh();
        $movement = StockMovement::create([
            'purchase_delivery_line_id' => $line->id, 'warehouse_id' => Warehouse::factory()->create()->id,
            'reference' => 'TRF-001', 'description' => 'Traslado a obra', 'category' => 'transfer',
            'quantity' => '-3', 'operation_date' => today(),
        ]);
        $this->assertSame('7.000000', app(ProjectCosting::class)->available($line));
        $movement->forceFill(['status' => 'posted'])->save();
        $target = PurchaseDeliveryLine::findOrFail($movement->fresh()->target_line_id);
        $this->assertSame('3.000000', app(ProjectCosting::class)->available($target));
        $this->assertSame('7.000000', app(ProjectCosting::class)->available($line, false));
        $this->assertSame('300.00', $target->net_total);
        $this->assertSame($line->base_unit_cost, $target->base_unit_cost);
        $this->expectException(ValidationException::class);
        $movement->forceFill(['status' => 'posted'])->save();
    }

    public function test_supplier_return_cannot_exceed_unreserved_stock(): void
    {
        $line = PurchaseDeliveryLine::factory()->create(['quantity' => '10']);
        app(ProjectCosting::class)->receive($line->deliveryNote);
        $line->refresh();
        $this->expectException(ValidationException::class);
        StockMovement::create([
            'purchase_delivery_line_id' => $line->id, 'reference' => 'DEV-001', 'description' => 'Devolución',
            'category' => 'supplier_return', 'quantity' => '-11', 'operation_date' => today(),
        ]);
    }
}
