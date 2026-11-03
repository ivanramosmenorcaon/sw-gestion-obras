<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductCategories\Pages\CreateProductCategory;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\SupplierProducts\Pages\CreateSupplierProduct;
use App\Filament\Resources\TaxProfiles\Pages\CreateTaxProfile;
use App\Filament\Resources\TaxProfiles\TaxProfileResource;
use App\Filament\Resources\TaxRates\TaxRateResource;
use App\Filament\Resources\WorkOrderContractorAssignments\Pages\CreateWorkOrderContractorAssignment;
use App\Filament\Resources\WorkOrderLines\Pages\CreateWorkOrderLine;
use App\Filament\Resources\WorkOrders\Pages\CreateWorkOrder;
use App\Models\Party;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkOrder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RelatedRecordSelectTest extends TestCase
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

    #[DataProvider('resources')]
    public function test_all_business_relationship_selects_offer_authorized_creation(string $resource): void
    {
        $this->actingAs(User::factory()->create());
        $page = Livewire::test($resource::getPages()['create']->getPage());

        foreach ($page->instance()->form->getFlatFields(withHidden: true) as $field) {
            if (! $field instanceof Select || ! $field->hasRelationship()) {
                continue;
            }
            $relatedResource = Filament::getModelResource($field->getRelationship()->getRelated());
            if ($relatedResource === null) {
                $this->assertNull($field->getCreateOptionAction(), $field->getStatePath());

                continue;
            }
            $action = $field->getCreateOptionAction();
            $this->assertNotNull($action, $field->getStatePath());
            $this->assertTrue($action->isAuthorized(), $field->getStatePath());
        }
    }

    /** @return array<string, array{class-string}> */
    public static function resources(): array
    {
        $resources = array_map(static fn (array $resource): array => [$resource[0]], FilamentResourcesTest::resources());
        $resources['tax_rates'] = [TaxRateResource::class];
        $resources['tax_profiles'] = [TaxProfileResource::class];

        return $resources;
    }

    public function test_a_customer_can_be_created_selected_and_used_without_leaving_the_project(): void
    {
        $this->actingAs(User::factory()->create());
        $page = Livewire::test(CreateProject::class);

        $page->callAction(TestAction::make('createOption')->schemaComponent('customer_id'), ['type' => 'organization', 'legal_name' => 'Cliente desde proyecto'])->assertHasNoFormErrors();

        $customer = Party::query()->where('legal_name', 'Cliente desde proyecto')->firstOrFail();
        $page->assertFormSet(['customer_id' => $customer->id]);
        $this->assertDatabaseHas('party_roles', ['party_id' => $customer->id, 'role' => 'customer']);

        $page->fillForm(['reference' => 'P-INLINE', 'name' => 'Obra nueva'])->call('create')->assertHasNoFormErrors()->assertNotified();

        $this->assertDatabaseHas('projects', ['reference' => 'P-INLINE', 'customer_id' => $customer->id]);
    }

    public function test_nested_creation_allows_a_customer_inside_a_new_project_from_an_order(): void
    {
        $this->actingAs(User::factory()->create());
        $page = Livewire::test(CreateWorkOrder::class)->mountAction(TestAction::make('createOption')->schemaComponent('project_id'));

        $page->callAction(TestAction::make('createOption')->schemaComponent('customer_id'), ['type' => 'organization', 'legal_name' => 'Cliente anidado'])->assertHasNoFormErrors();
        $customer = Party::query()->where('legal_name', 'Cliente anidado')->firstOrFail();
        $page->assertFormSet(['customer_id' => $customer->id], form: 'mountedActionSchema0')->fillForm(['reference' => 'P-NESTED', 'name' => 'Proyecto anidado'])->callMountedAction()->assertHasNoFormErrors();

        $project = Project::query()->where('reference', 'P-NESTED')->firstOrFail();
        $page->assertFormSet(['project_id' => $project->id]);
        $this->assertSame($customer->id, $project->customer_id);
    }

    public function test_supplier_creation_assigns_the_supplier_role(): void
    {
        $this->actingAs(User::factory()->create());

        $page = Livewire::test(CreateSupplierProduct::class)->callAction(TestAction::make('createOption')->schemaComponent('supplier_id'), ['type' => 'organization', 'legal_name' => 'Proveedor nuevo'])->assertHasNoFormErrors();

        $supplier = Party::query()->where('legal_name', 'Proveedor nuevo')->firstOrFail();
        $page->assertFormSet(['supplier_id' => $supplier->id]);
        $this->assertDatabaseHas('party_roles', ['party_id' => $supplier->id, 'role' => 'supplier']);
    }

    public function test_contractor_creation_assigns_the_subcontractor_role(): void
    {
        $this->actingAs(User::factory()->create());

        $page = Livewire::test(CreateWorkOrderContractorAssignment::class)
            ->callAction(TestAction::make('createOption')->schemaComponent('contractor_id'), ['type' => 'organization', 'legal_name' => 'Contratista nuevo'])->assertHasNoFormErrors();

        $contractor = Party::query()->where('legal_name', 'Contratista nuevo')->firstOrFail();
        $page->assertFormSet(['contractor_id' => $contractor->id]);
        $this->assertDatabaseHas('party_roles', ['party_id' => $contractor->id, 'role' => 'subcontractor']);
    }

    public function test_a_new_phase_is_scoped_to_the_selected_order(): void
    {
        $this->actingAs(User::factory()->create());
        $order = WorkOrder::factory()->create();
        $otherOrder = WorkOrder::factory()->create();

        $page = Livewire::test(CreateWorkOrderLine::class)->fillForm(['project_id' => $order->project_id, 'work_order_id' => $order->id])
            ->callAction(TestAction::make('createOption')->schemaComponent('work_order_phase_id'), ['work_order_id' => $otherOrder->id, 'code' => 'montaje', 'name' => 'Montaje'])->assertHasNoFormErrors();

        $this->assertDatabaseHas('work_order_phases', ['work_order_id' => $order->id, 'code' => 'montaje']);
        $this->assertDatabaseMissing('work_order_phases', ['work_order_id' => $otherOrder->id, 'code' => 'montaje']);
        $page->assertFormSet(['work_order_id' => $order->id]);
    }

    public function test_a_new_presentation_is_selected_and_copies_its_unit_and_factor(): void
    {
        $this->actingAs(User::factory()->create());
        $product = Product::factory()->create();

        $page = Livewire::test(CreateWorkOrderLine::class)->fillForm(['product_id' => $product->id])
            ->callAction(TestAction::make('createOption')->schemaComponent('product_unit_id'), ['code' => 'pack20', 'name' => 'Caja de 20', 'unit_id' => $product->base_unit_id, 'base_unit_factor' => '20'])->assertHasNoFormErrors();

        $presentation = $product->productUnits()->where('code', 'pack20')->firstOrFail();
        $page->assertFormSet(['product_id' => $product->id, 'product_unit_id' => $presentation->id, 'unit_id' => $product->base_unit_id, 'base_unit_factor' => '20.000000000000']);
    }

    public function test_rate_creation_keeps_the_tax_family_required_by_the_select(): void
    {
        $this->actingAs(User::factory()->create());

        $page = Livewire::test(CreateTaxProfile::class)->fillForm(['operation_class' => 'S1'])
            ->callAction(TestAction::make('createOption')->schemaComponent('tax_rate_id'), ['code' => 'INLINE21', 'name' => 'IVA 21 %', 'kind' => 'withholding', 'percentage' => '21', 'valid_from' => '2026-01-01'])->assertHasNoFormErrors();

        $rate = TaxRate::query()->where('code', 'INLINE21')->firstOrFail();
        $this->assertSame('vat', $rate->kind);
        $page->assertFormSet(['tax_rate_id' => $rate->id]);
    }

    public function test_inline_validation_returns_errors_without_creating_a_record(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreateProject::class)->callAction(TestAction::make('createOption')->schemaComponent('customer_id'), ['type' => 'organization', 'legal_name' => null])->assertHasFormErrors(['legal_name' => 'required']);

        $this->assertDatabaseCount('parties', 0);
        $this->assertDatabaseCount('party_roles', 0);
    }

    public function test_related_creation_respects_the_related_resource_policy(): void
    {
        $this->actingAs(User::factory()->create());
        Gate::policy(Party::class, DenyInlinePartyCreation::class);

        Livewire::test(CreateProject::class)->assertActionHidden(TestAction::make('createOption')->schemaComponent('customer_id'));

        $this->assertDatabaseCount('parties', 0);
    }

    public function test_official_catalogs_and_account_selects_do_not_offer_record_creation(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreateProject::class)
            ->assertActionDoesNotExist(TestAction::make('createOption')->schemaComponent('country_code'))
            ->assertActionDoesNotExist(TestAction::make('createOption')->schemaComponent('manager_id'));
    }

    public function test_a_parent_category_can_be_created_from_a_category_form(): void
    {
        $this->actingAs(User::factory()->create());

        $page = Livewire::test(CreateProductCategory::class)->callAction(TestAction::make('createOption')->schemaComponent('parent_id'), ['code' => 'ELECTRICIDAD', 'name' => 'Electricidad'])->assertHasNoFormErrors();

        $parent = ProductCategory::query()->where('code', 'ELECTRICIDAD')->firstOrFail();
        $page->assertFormSet(['parent_id' => $parent->id]);
    }

    public function test_a_unit_can_be_created_from_the_product_form(): void
    {
        $this->actingAs(User::factory()->create());

        $page = Livewire::test(CreateProduct::class)->callAction(TestAction::make('createOption')->schemaComponent('base_unit_id'), ['code' => 'm', 'name' => 'Metro', 'symbol' => 'm', 'magnitude' => 'length'])->assertHasNoFormErrors();

        $unit = Unit::query()->where('code', 'm')->firstOrFail();
        $page->assertFormSet(['base_unit_id' => $unit->id]);
    }
}

class DenyInlinePartyCreation
{
    public function create(User $user): bool
    {
        return false;
    }
}
