<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\Employee;
use App\Models\Party;
use App\Models\PartyAddress;
use App\Models\PartyContact;
use App\Models\PartyCustomerSetting;
use App\Models\PartyRole;
use App\Models\PartySupplierSetting;
use App\Models\PaymentTerm;
use App\Models\PaymentTermInstallment;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\Project;
use App\Models\ProjectChapter;
use App\Models\SupplierProduct;
use App\Models\Unit;
use App\Models\UnitConversion;
use App\Models\WorkOrder;
use App\Models\WorkOrderContractorAssignment;
use App\Models\WorkOrderEmployeeAssignment;
use App\Models\WorkOrderLine;
use App\Models\WorkOrderPhase;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BusinessModelsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    #[DataProvider('models')]
    public function test_factories_create_valid_records_and_relationships(string $model): void
    {
        $record = $model::factory()->create();

        $this->assertModelExists($record);
    }

    /** @return array<string, array{class-string}> */
    public static function models(): array
    {
        return [
            'projects' => [Project::class],
            'project_chapters' => [ProjectChapter::class],
            'employees' => [Employee::class],
            'work_orders' => [WorkOrder::class],
            'work_order_phases' => [WorkOrderPhase::class],
            'work_order_lines' => [WorkOrderLine::class],
            'work_order_employee_assignments' => [WorkOrderEmployeeAssignment::class],
            'work_order_contractor_assignments' => [WorkOrderContractorAssignment::class],
            'company_settings' => [CompanySetting::class],
            'parties' => [Party::class],
            'party_roles' => [PartyRole::class],
            'party_addresses' => [PartyAddress::class],
            'party_contacts' => [PartyContact::class],
            'payment_terms' => [PaymentTerm::class],
            'payment_term_installments' => [PaymentTermInstallment::class],
            'units' => [Unit::class],
            'unit_conversions' => [UnitConversion::class],
            'product_categories' => [ProductCategory::class],
            'products' => [Product::class],
            'product_units' => [ProductUnit::class],
            'supplier_products' => [SupplierProduct::class],
            'price_lists' => [PriceList::class],
            'price_list_items' => [PriceListItem::class],
            'party_customer_settings' => [PartyCustomerSetting::class],
            'party_supplier_settings' => [PartySupplierSetting::class],
        ];
    }

    public function test_tax_identity_is_normalized_before_saving(): void
    {
        $party = Party::factory()->create(['tax_country_code' => 'es', 'tax_identification_number' => ' b 12345678 ']);

        $this->assertDatabaseHas('parties', ['id' => $party->id, 'tax_country_code' => 'ES', 'tax_identification_number' => 'B12345678']);
    }

    public function test_category_cycles_are_rejected(): void
    {
        $parent = ProductCategory::factory()->create();
        $child = ProductCategory::factory()->create(['parent_id' => $parent->id]);

        $this->expectException(ValidationException::class);

        $parent->update(['parent_id' => $child->id]);
    }

    public function test_incompatible_unit_conversions_are_rejected(): void
    {
        $mass = Unit::factory()->create(['magnitude' => 'mass']);
        $length = Unit::factory()->create(['magnitude' => 'length']);

        $this->expectException(ValidationException::class);

        UnitConversion::factory()->create(['from_unit_id' => $mass->id, 'to_unit_id' => $length->id]);
    }

    public function test_payment_percentages_cannot_exceed_one_hundred(): void
    {
        $term = PaymentTerm::factory()->create();
        PaymentTermInstallment::factory()->create(['payment_term_id' => $term->id, 'sequence' => 1, 'percentage' => '60']);

        $this->expectException(ValidationException::class);

        PaymentTermInstallment::factory()->create(['payment_term_id' => $term->id, 'sequence' => 2, 'percentage' => '50']);
    }

    public function test_overlapping_prices_are_rejected(): void
    {
        $price = PriceListItem::factory()->create();

        $this->expectException(ValidationException::class);

        PriceListItem::factory()->create(['price_list_id' => $price->price_list_id, 'product_id' => $price->product_id, 'product_unit_id' => $price->product_unit_id, 'valid_from' => '2026-12-01']);
    }

    public function test_decimal_costs_are_returned_as_strings(): void
    {
        $line = WorkOrderLine::factory()->create(['estimated_unit_cost' => '12.123456']);

        $this->assertSame('12.123456', $line->fresh()->estimated_unit_cost);
    }

    public function test_the_base_presentation_cannot_be_deleted(): void
    {
        $product = Product::factory()->create();

        $this->expectException(ValidationException::class);

        $product->productUnits()->firstOrFail()->delete();
    }
}
