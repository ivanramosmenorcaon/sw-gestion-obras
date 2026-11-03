<?php

namespace Tests\Feature;

use App\Filament\Resources\CompanySettings\CompanySettingResource;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Employees\RelationManagers\WorkOrderEmployeeAssignmentsRelationManager;
use App\Filament\Resources\Parties\Pages\EditParty;
use App\Filament\Resources\Parties\PartyResource;
use App\Filament\Resources\Parties\RelationManagers\PartyAddressesRelationManager;
use App\Filament\Resources\Parties\RelationManagers\PartyContactsRelationManager;
use App\Filament\Resources\Parties\RelationManagers\PartyRolesRelationManager;
use App\Filament\Resources\Parties\RelationManagers\ProjectsRelationManager;
use App\Filament\Resources\Parties\RelationManagers\SupplierProductsRelationManager;
use App\Filament\Resources\PartyAddresses\PartyAddressResource;
use App\Filament\Resources\PartyContacts\PartyContactResource;
use App\Filament\Resources\PartyRoles\PartyRoleResource;
use App\Filament\Resources\PaymentTermInstallments\PaymentTermInstallmentResource;
use App\Filament\Resources\PaymentTerms\Pages\EditPaymentTerm;
use App\Filament\Resources\PaymentTerms\PaymentTermResource;
use App\Filament\Resources\PaymentTerms\RelationManagers\PaymentTermInstallmentsRelationManager;
use App\Filament\Resources\PriceListItems\PriceListItemResource;
use App\Filament\Resources\PriceLists\Pages\EditPriceList;
use App\Filament\Resources\PriceLists\PriceListResource;
use App\Filament\Resources\ProductCategories\ProductCategoryResource;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Products\RelationManagers\PriceListItemsRelationManager;
use App\Filament\Resources\Products\RelationManagers\ProductUnitsRelationManager;
use App\Filament\Resources\ProductUnits\ProductUnitResource;
use App\Filament\Resources\ProjectChapters\ProjectChapterResource;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Projects\RelationManagers\ProjectChaptersRelationManager;
use App\Filament\Resources\Projects\RelationManagers\WorkOrdersRelationManager;
use App\Filament\Resources\SupplierProducts\SupplierProductResource;
use App\Filament\Resources\UnitConversions\UnitConversionResource;
use App\Filament\Resources\Units\Pages\CreateUnit;
use App\Filament\Resources\Units\Pages\EditUnit;
use App\Filament\Resources\Units\RelationManagers\UnitConversionsByFromUnitRelationManager;
use App\Filament\Resources\Units\UnitResource;
use App\Filament\Resources\WorkOrderContractorAssignments\WorkOrderContractorAssignmentResource;
use App\Filament\Resources\WorkOrderEmployeeAssignments\WorkOrderEmployeeAssignmentResource;
use App\Filament\Resources\WorkOrderLines\Pages\EditWorkOrderLine;
use App\Filament\Resources\WorkOrderLines\WorkOrderLineResource;
use App\Filament\Resources\WorkOrderPhases\WorkOrderPhaseResource;
use App\Filament\Resources\WorkOrders\Pages\EditWorkOrder;
use App\Filament\Resources\WorkOrders\RelationManagers\WorkOrderContractorAssignmentsRelationManager;
use App\Filament\Resources\WorkOrders\RelationManagers\WorkOrderLinesRelationManager;
use App\Filament\Resources\WorkOrders\RelationManagers\WorkOrderPhasesRelationManager;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\CompanySetting;
use App\Models\Employee;
use App\Models\Party;
use App\Models\PartyAddress;
use App\Models\PartyContact;
use App\Models\PartyRole;
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
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderContractorAssignment;
use App\Models\WorkOrderEmployeeAssignment;
use App\Models\WorkOrderLine;
use App\Models\WorkOrderPhase;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FilamentResourcesTest extends TestCase
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
    public function test_lists_create_and_edit_pages_render(string $resource, string $model): void
    {
        $this->actingAs(User::factory()->create());
        $pages = $resource::getPages();

        Livewire::test($pages['create']->getPage())->assertSuccessful();
        $record = $model::factory()->create();
        Livewire::test($pages['index']->getPage())->assertSuccessful()->assertCanSeeTableRecords([$record]);
        Livewire::test($pages['edit']->getPage(), ['record' => $record->getKey()])->assertSuccessful();
    }

    /** @return array<string, array{class-string, class-string}> */
    public static function resources(): array
    {
        return [
            'projects' => [ProjectResource::class, Project::class],
            'project_chapters' => [ProjectChapterResource::class, ProjectChapter::class],
            'employees' => [EmployeeResource::class, Employee::class],
            'work_orders' => [WorkOrderResource::class, WorkOrder::class],
            'work_order_phases' => [WorkOrderPhaseResource::class, WorkOrderPhase::class],
            'work_order_lines' => [WorkOrderLineResource::class, WorkOrderLine::class],
            'work_order_employee_assignments' => [WorkOrderEmployeeAssignmentResource::class, WorkOrderEmployeeAssignment::class],
            'work_order_contractor_assignments' => [WorkOrderContractorAssignmentResource::class, WorkOrderContractorAssignment::class],
            'company_settings' => [CompanySettingResource::class, CompanySetting::class],
            'parties' => [PartyResource::class, Party::class],
            'party_roles' => [PartyRoleResource::class, PartyRole::class],
            'party_addresses' => [PartyAddressResource::class, PartyAddress::class],
            'party_contacts' => [PartyContactResource::class, PartyContact::class],
            'payment_terms' => [PaymentTermResource::class, PaymentTerm::class],
            'payment_term_installments' => [PaymentTermInstallmentResource::class, PaymentTermInstallment::class],
            'units' => [UnitResource::class, Unit::class],
            'unit_conversions' => [UnitConversionResource::class, UnitConversion::class],
            'product_categories' => [ProductCategoryResource::class, ProductCategory::class],
            'products' => [ProductResource::class, Product::class],
            'product_units' => [ProductUnitResource::class, ProductUnit::class],
            'supplier_products' => [SupplierProductResource::class, SupplierProduct::class],
            'price_lists' => [PriceListResource::class, PriceList::class],
            'price_list_items' => [PriceListItemResource::class, PriceListItem::class],
        ];
    }

    #[DataProvider('resources')]
    public function test_each_resource_can_create_a_valid_record(string $resource, string $model): void
    {
        $this->actingAs(User::factory()->create());
        $data = $model::factory()->raw();
        $data = array_merge(array_intersect_key($data, ['project_id' => true]), $data);
        $count = $model::query()->count();

        Livewire::test($resource::getPages()['create']->getPage())
            ->fillForm($data)->call('create')->assertHasNoFormErrors()->assertNotified();

        $this->assertSame($count + 1, $model::query()->count());
    }

    #[DataProvider('relationManagers')]
    public function test_relation_manager_and_create_form_render(string $manager, string $model, string $page): void
    {
        $this->actingAs(User::factory()->create());
        $owner = $model::factory()->create();

        Livewire::test($manager, ['ownerRecord' => $owner, 'pageClass' => $page])
            ->assertSuccessful()
            ->mountAction(TestAction::make('create')->table())
            ->assertHasNoFormErrors();
    }

    /** @return array<string, array{class-string, class-string, class-string}> */
    public static function relationManagers(): array
    {
        return [
            'projects.projectChapters' => [ProjectChaptersRelationManager::class, Project::class, EditProject::class],
            'projects.workOrders' => [WorkOrdersRelationManager::class, Project::class, EditProject::class],
            'employees.workOrderEmployeeAssignments' => [WorkOrderEmployeeAssignmentsRelationManager::class, Employee::class, EditEmployee::class],
            'work_orders.workOrderPhases' => [WorkOrderPhasesRelationManager::class, WorkOrder::class, EditWorkOrder::class],
            'work_orders.workOrderLines' => [WorkOrderLinesRelationManager::class, WorkOrder::class, EditWorkOrder::class],
            'work_orders.workOrderEmployeeAssignments' => [\App\Filament\Resources\WorkOrders\RelationManagers\WorkOrderEmployeeAssignmentsRelationManager::class, WorkOrder::class, EditWorkOrder::class],
            'work_orders.workOrderContractorAssignments' => [WorkOrderContractorAssignmentsRelationManager::class, WorkOrder::class, EditWorkOrder::class],
            'parties.partyRoles' => [PartyRolesRelationManager::class, Party::class, EditParty::class],
            'parties.partyAddresses' => [PartyAddressesRelationManager::class, Party::class, EditParty::class],
            'parties.partyContacts' => [PartyContactsRelationManager::class, Party::class, EditParty::class],
            'parties.supplierProducts' => [SupplierProductsRelationManager::class, Party::class, EditParty::class],
            'parties.projects' => [ProjectsRelationManager::class, Party::class, EditParty::class],
            'payment_terms.paymentTermInstallments' => [PaymentTermInstallmentsRelationManager::class, PaymentTerm::class, EditPaymentTerm::class],
            'units.unitConversionsByFromUnit' => [UnitConversionsByFromUnitRelationManager::class, Unit::class, EditUnit::class],
            'products.productUnits' => [ProductUnitsRelationManager::class, Product::class, EditProduct::class],
            'products.supplierProducts' => [\App\Filament\Resources\Products\RelationManagers\SupplierProductsRelationManager::class, Product::class, EditProduct::class],
            'products.priceListItems' => [PriceListItemsRelationManager::class, Product::class, EditProduct::class],
            'price_lists.priceListItems' => [\App\Filament\Resources\PriceLists\RelationManagers\PriceListItemsRelationManager::class, PriceList::class, EditPriceList::class],
        ];
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(ProjectResource::getUrl())->assertRedirect(Filament::getLoginUrl());
    }

    public function test_authenticated_navigation_and_validation_are_in_spanish(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(ProjectResource::getUrl())
            ->assertOk()->assertSee('Obras')->assertSee('Catálogo')->assertSee('Configuración');
        Livewire::test(CreateUnit::class)
            ->fillForm(['code' => null, 'name' => null, 'symbol' => null, 'magnitude' => null])
            ->call('create')->assertHasFormErrors(['code' => 'required', 'name' => 'required'])
            ->assertSee('obligatorio');

        $this->assertDatabaseCount('units', 0);
        $this->assertSame('es', app()->getLocale());
    }

    public function test_product_creation_saves_a_base_presentation_and_edit_preserves_it(): void
    {
        $this->actingAs(User::factory()->create());
        $unit = Unit::factory()->create();

        Livewire::test(CreateProduct::class)
            ->fillForm(['reference' => 'MAT-001', 'name' => 'Material', 'type' => 'material', 'base_unit_id' => $unit->id])
            ->call('create')->assertHasNoFormErrors()->assertNotified();

        $product = Product::query()->where('reference', 'MAT-001')->firstOrFail();
        $this->assertDatabaseHas('product_units', ['product_id' => $product->id, 'code' => 'base', 'unit_id' => $unit->id, 'base_unit_factor' => 1]);

        Livewire::test(EditProduct::class, ['record' => $product->id])
            ->fillForm(['name' => 'Material actualizado'])->call('save')->assertHasNoFormErrors()->assertNotified();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Material actualizado']);
        $this->assertDatabaseCount('product_units', 1);
    }

    public function test_relation_manager_creates_a_phase_under_its_owner(): void
    {
        $this->actingAs(User::factory()->create());
        $order = WorkOrder::factory()->create();

        Livewire::test(WorkOrderPhasesRelationManager::class, [
            'ownerRecord' => $order,
            'pageClass' => EditWorkOrder::class,
        ])->callAction(TestAction::make('create')->table(), ['code' => 'montaje', 'name' => 'Montaje'])
            ->assertHasNoFormErrors()->assertNotified();

        $this->assertDatabaseHas('work_order_phases', ['work_order_id' => $order->id, 'code' => 'montaje', 'name' => 'Montaje']);
    }

    public function test_cross_project_chapter_is_rejected_in_the_form(): void
    {
        $this->actingAs(User::factory()->create());
        $line = WorkOrderLine::factory()->create();
        $otherChapter = ProjectChapter::factory()->create();

        Livewire::test(EditWorkOrderLine::class, ['record' => $line->id])
            ->fillForm(['project_chapter_id' => $otherChapter->id])->call('save')->assertHasFormErrors(['project_chapter_id']);

        $this->assertDatabaseHas('work_order_lines', ['id' => $line->id, 'project_chapter_id' => null]);
    }

    public function test_customer_commercial_settings_are_saved_from_the_party_form(): void
    {
        $this->actingAs(User::factory()->create());
        $party = Party::factory()->customer()->create();
        $term = PaymentTerm::factory()->create();
        $list = PriceList::factory()->create();

        Livewire::test(EditParty::class, ['record' => $party->id])
            ->fillForm(['customerSettings' => ['payment_term_id' => $term->id, 'price_list_id' => $list->id, 'customer_reference' => 'CLI-001']])
            ->call('save')->assertHasNoFormErrors()->assertNotified();

        $this->assertDatabaseHas('party_customer_settings', ['party_id' => $party->id, 'payment_term_id' => $term->id, 'price_list_id' => $list->id, 'customer_reference' => 'CLI-001']);
    }

    public function test_relation_manager_displays_percentage_errors_in_spanish(): void
    {
        $this->actingAs(User::factory()->create());
        $term = PaymentTerm::factory()->create();
        PaymentTermInstallment::factory()->create(['payment_term_id' => $term->id, 'sequence' => 1, 'percentage' => '60']);

        Livewire::test(PaymentTermInstallmentsRelationManager::class, [
            'ownerRecord' => $term,
            'pageClass' => EditPaymentTerm::class,
        ])->callAction(TestAction::make('create')->table(), ['sequence' => 2, 'percentage' => '50'])
            ->assertHasFormErrors(['percentage' => 'La suma de los porcentajes no puede superar el 100 %.']);

        $this->assertDatabaseCount('payment_term_installments', 1);
    }

    public function test_line_relation_manager_preserves_the_owners_project(): void
    {
        $this->actingAs(User::factory()->create());
        $order = WorkOrder::factory()->create();
        $unit = Unit::factory()->create();

        Livewire::test(WorkOrderLinesRelationManager::class, [
            'ownerRecord' => $order,
            'pageClass' => EditWorkOrder::class,
        ])->callAction(TestAction::make('create')->table(), [
            'position' => 1, 'type' => 'service', 'description' => 'Trabajo a medida',
            'unit_id' => $unit->id, 'base_unit_id' => $unit->id, 'unit_label' => $unit->name,
            'planned_quantity' => '2.5',
        ])->assertHasNoFormErrors()->assertNotified();

        $this->assertDatabaseHas('work_order_lines', ['work_order_id' => $order->id, 'project_id' => $order->project_id, 'description' => 'Trabajo a medida', 'planned_quantity' => '2.5']);
    }
}
