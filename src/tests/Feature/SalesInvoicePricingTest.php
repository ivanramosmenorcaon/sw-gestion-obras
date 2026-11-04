<?php

namespace Tests\Feature;

use App\Filament\Resources\SalesInvoices\Pages\EditSalesInvoice;
use App\Filament\Resources\SalesInvoices\RelationManagers\LinesRelationManager;
use App\Models\PartyCustomerSetting;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\TaxProfile;
use App\Models\User;
use App\Support\SalesPrice;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SalesInvoicePricingTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();
    }

    public function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    public function test_selecting_a_product_loads_customer_price_and_preserves_manual_override_on_quantity_change(): void
    {
        $this->actingAs(User::factory()->create());
        $invoice = SalesInvoice::factory()->create(['operation_date' => '2026-11-04']);
        $product = Product::factory()->create(['sales_tax_profile_id' => TaxProfile::factory()->create()->id]);
        $list = PriceList::factory()->create();
        PartyCustomerSetting::factory()->create(['party_id' => $invoice->customer_id, 'price_list_id' => $list->id]);
        PriceListItem::factory()->create(['price_list_id' => $list->id, 'product_id' => $product->id, 'unit_price' => '0.8']);
        PriceListItem::factory()->create(['price_list_id' => $list->id, 'product_id' => $product->id, 'minimum_quantity' => '10', 'unit_price' => '0.6']);

        $page = Livewire::test(LinesRelationManager::class, ['ownerRecord' => $invoice, 'pageClass' => EditSalesInvoice::class])->mountAction(TestAction::make('create')->table())
            ->set('mountedActions.0.data.product_id', $product->id)->assertSet('mountedActions.0.data.unit_price', '0.800000')
            ->set('mountedActions.0.data.quantity', '10')->assertSet('mountedActions.0.data.unit_price', '0.600000')
            ->set('mountedActions.0.data.unit_price', '0.55')->set('mountedActions.0.data.quantity', '20')->assertSet('mountedActions.0.data.unit_price', '0.55');
        $page->callMountedAction()->assertHasNoFormErrors();
        $this->assertDatabaseHas('sales_invoice_lines', ['sales_invoice_id' => $invoice->id, 'product_id' => $product->id, 'unit_price' => '0.55', 'quantity' => '20']);
    }

    public function test_price_uses_operation_date_and_does_not_fall_back_from_expired_customer_tariff(): void
    {
        $invoice = SalesInvoice::factory()->create(['operation_date' => '2026-11-04']);
        $product = Product::factory()->create();
        $list = PriceList::factory()->create(['valid_until' => '2026-11-03']);
        PartyCustomerSetting::factory()->create(['party_id' => $invoice->customer_id, 'price_list_id' => $list->id]);
        PriceListItem::factory()->create(['price_list_id' => $list->id, 'product_id' => $product->id]);
        PriceListItem::factory()->create(['product_id' => $product->id, 'unit_price' => '99']);

        $this->assertNull(app(SalesPrice::class)->resolve($invoice, $product->id));
        $list->update(['valid_until' => '2026-11-04']);
        $this->assertSame('1.000000', app(SalesPrice::class)->resolve($invoice, $product->id));
    }

    public function test_unique_tariff_can_be_used_without_customer_assignment_but_ambiguous_prices_are_not_guessed(): void
    {
        $invoice = SalesInvoice::factory()->create(['operation_date' => '2026-11-04']);
        $product = Product::factory()->create();
        PriceListItem::factory()->create(['product_id' => $product->id, 'unit_price' => '0']);
        $this->assertSame('0.000000', app(SalesPrice::class)->resolve($invoice, $product->id));
        PriceListItem::factory()->create(['product_id' => $product->id, 'unit_price' => '2']);
        $this->assertNull(app(SalesPrice::class)->resolve($invoice, $product->id));
    }

    public function test_ineligible_dates_currencies_and_package_prices_are_not_used_as_base_unit_prices(): void
    {
        $invoice = SalesInvoice::factory()->create(['operation_date' => '2026-11-04']);
        $product = Product::factory()->create();
        $item = PriceListItem::factory()->create(['product_id' => $product->id, 'valid_from' => '2026-11-05']);
        $resolver = app(SalesPrice::class);
        $this->assertNull($resolver->resolve($invoice, $product->id));
        $item->update(['valid_from' => '2026-11-01', 'valid_until' => '2026-11-03']);
        $this->assertNull($resolver->resolve($invoice, $product->id));
        $item->update(['valid_until' => null]);
        $item->priceList->update(['currency_code' => 'USD']);
        $this->assertNull($resolver->resolve($invoice, $product->id));
        $item->priceList->update(['currency_code' => 'EUR']);
        $package = $product->productUnits()->create(['code' => 'PACK', 'name' => 'Paquete', 'unit_id' => $product->base_unit_id, 'base_unit_factor' => '12']);
        $item->update(['product_unit_id' => $package->id]);
        $this->assertNull($resolver->resolve($invoice, $product->id));
    }

    public function test_switching_to_a_product_without_price_clears_the_previous_price(): void
    {
        $this->actingAs(User::factory()->create());
        $invoice = SalesInvoice::factory()->create(['operation_date' => '2026-11-04']);
        $product = Product::factory()->create();
        $other = Product::factory()->create();
        PriceListItem::factory()->create(['product_id' => $product->id, 'unit_price' => '12']);
        Livewire::test(LinesRelationManager::class, ['ownerRecord' => $invoice, 'pageClass' => EditSalesInvoice::class])->mountAction(TestAction::make('create')->table())
            ->set('mountedActions.0.data.product_id', $product->id)->assertSet('mountedActions.0.data.unit_price', '12.000000')
            ->set('mountedActions.0.data.product_id', $other->id)->assertSet('mountedActions.0.data.unit_price', null);
    }
}
