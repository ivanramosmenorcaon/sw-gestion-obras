<?php

namespace Tests\Feature;

use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\RelationManagers\WorkOrdersRelationManager;
use App\Filament\Resources\WorkOrders\Pages\EditWorkOrder;
use App\Filament\Resources\WorkOrders\Pages\ListWorkOrders;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\Budget;
use App\Models\ProjectChapter;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderContractorAssignment;
use App\Models\WorkOrderEmployeeAssignment;
use App\Models\WorkOrderLine;
use App\Models\WorkOrderPhase;
use App\Support\BusinessRecordValidator;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class DraftWorkOrderDeletionTest extends TestCase
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

    public function test_populated_draft_can_be_deleted_from_the_edit_page(): void
    {
        $this->actingAs(User::factory()->create());
        $order = $this->populatedDraft();
        $chapter = $order->project->projectChapters()->sole();
        $other = WorkOrder::factory()->create();
        $otherLine = WorkOrderLine::factory()->for($other)->create();

        Livewire::test(EditWorkOrder::class, ['record' => $order->id])
            ->callAction('delete')->assertNotified()->assertRedirect(WorkOrderResource::getUrl());

        $this->assertModelMissing($order);
        $this->assertModelExists($chapter);
        $this->assertModelExists($other);
        $this->assertModelExists($otherLine);
        $this->assertDatabaseMissing('work_order_lines', ['work_order_id' => $order->id]);
        $this->assertDatabaseMissing('work_order_phases', ['work_order_id' => $order->id]);
        $this->assertDatabaseMissing('work_order_employee_assignments', ['work_order_id' => $order->id]);
        $this->assertDatabaseMissing('work_order_contractor_assignments', ['work_order_id' => $order->id]);
    }

    public function test_draft_can_be_deleted_from_the_work_orders_list(): void
    {
        $this->actingAs(User::factory()->create());
        $order = $this->populatedDraft();

        Livewire::test(ListWorkOrders::class)
            ->callAction(TestAction::make('delete')->table($order))->assertNotified();

        $this->assertModelMissing($order);
        $this->assertDatabaseMissing('work_order_lines', ['work_order_id' => $order->id]);
    }

    public function test_linked_invoices_are_visible_in_the_order_list_and_edit_page(): void
    {
        $this->actingAs(User::factory()->create());
        $order = $this->populatedDraft();
        SalesInvoice::factory()->count(2)->create([
            'customer_id' => $order->project->customer_id,
            'work_order_id' => $order->id,
        ]);

        Livewire::test(ListWorkOrders::class)
            ->assertCanSeeTableRecords([$order])
            ->assertSee('2 facturas vinculadas');

        Livewire::test(EditWorkOrder::class, ['record' => $order->id])
            ->assertSee('2 facturas vinculadas');
    }

    public function test_draft_can_be_deleted_from_the_project_relation_manager(): void
    {
        $this->actingAs(User::factory()->create());
        $order = $this->populatedDraft();

        Livewire::test(WorkOrdersRelationManager::class, ['ownerRecord' => $order->project, 'pageClass' => EditProject::class])
            ->callAction(TestAction::make('delete')->table($order))->assertNotified();

        $this->assertModelMissing($order);
        $this->assertDatabaseMissing('work_order_lines', ['work_order_id' => $order->id]);
    }

    #[DataProvider('protectedStatuses')]
    public function test_populated_non_draft_orders_remain_protected(string $status): void
    {
        $order = $this->populatedDraft();
        if ($status === 'completed') {
            foreach ($order->workOrderPhases as $phase) {
                $phase->update(['status' => 'completed']);
            }
        }
        $order->update(['status' => $status]);
        $this->assertFalse(BusinessRecordValidator::canDelete($order));

        try {
            $order->delete();
            $this->fail('Se esperaba un error al borrar una orden con datos que no está en borrador.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('id', $exception->errors());
        }

        $this->assertModelExists($order);
        $this->assertSame(1, $order->workOrderLines()->count());
        $this->assertSame(1, $order->workOrderPhases()->count());
        $this->assertSame(1, $order->workOrderEmployeeAssignments()->count());
        $this->assertSame(1, $order->workOrderContractorAssignments()->count());
    }

    public function test_orders_created_from_accepted_budgets_show_why_deletion_is_unavailable(): void
    {
        $this->actingAs(User::factory()->create());
        $order = $this->populatedDraft();
        $budget = Budget::factory()->create();
        $order->forceFill(['accepted_budget_id' => $budget->id])->save();

        Livewire::test(EditWorkOrder::class, ['record' => $order->id])
            ->assertActionDisabled('delete');

        Livewire::test(ListWorkOrders::class)
            ->assertActionDisabled(TestAction::make('delete')->table($order));

        Livewire::test(WorkOrdersRelationManager::class, ['ownerRecord' => $order->project, 'pageClass' => EditProject::class])
            ->assertActionDisabled(TestAction::make('delete')->table($order));

        $this->assertModelExists($order);
    }

    /** @return array<string, array{string}> */
    public static function protectedStatuses(): array
    {
        return [
            'planned' => ['planned'], 'in progress' => ['in_progress'], 'on hold' => ['on_hold'],
            'completed' => ['completed'], 'cancelled' => ['cancelled'],
        ];
    }

    public function test_changing_persisted_status_prevents_deleting_a_stale_draft(): void
    {
        $order = $this->populatedDraft();
        $anotherInstance = WorkOrder::findOrFail($order->id);
        $anotherInstance->update(['status' => 'in_progress']);

        try {
            $order->delete();
            $this->fail('Se esperaba que se comprobara el estado guardado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('id', $exception->errors());
        }

        $this->assertSame('in_progress', $order->fresh()->status);
        $this->assertSame(1, $order->workOrderLines()->count());
    }

    public function test_delete_authorization_is_respected_for_drafts(): void
    {
        $this->actingAs(User::factory()->create());
        $order = $this->populatedDraft();
        Gate::policy(WorkOrder::class, DenyDraftWorkOrderDeletion::class);

        Livewire::test(EditWorkOrder::class, ['record' => $order->id])->assertActionHidden('delete');
        Livewire::test(ListWorkOrders::class)->assertActionHidden(TestAction::make('delete')->table($order));
        Livewire::test(WorkOrdersRelationManager::class, ['ownerRecord' => $order->project, 'pageClass' => EditProject::class])
            ->assertActionHidden(TestAction::make('delete')->table($order));

        $this->assertModelExists($order);
        $this->assertSame(1, $order->workOrderLines()->count());
    }

    public function test_children_are_restored_if_deleting_the_order_fails(): void
    {
        $order = $this->populatedDraft();
        WorkOrder::deleted(function (WorkOrder $record) use ($order): void {
            if ($record->id === $order->id) {
                throw new RuntimeException('Simulated deletion failure');
            }
        });

        try {
            $order->delete();
            $this->fail('Se esperaba un fallo de borrado.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated deletion failure', $exception->getMessage());
        }

        $this->assertDatabaseHas('work_orders', ['id' => $order->id]);
        $this->assertSame(1, WorkOrderLine::where('work_order_id', $order->id)->count());
        $this->assertSame(1, WorkOrderPhase::where('work_order_id', $order->id)->count());
        $this->assertSame(1, WorkOrderEmployeeAssignment::where('work_order_id', $order->id)->count());
        $this->assertSame(1, WorkOrderContractorAssignment::where('work_order_id', $order->id)->count());
    }

    private function populatedDraft(): WorkOrder
    {
        $order = WorkOrder::factory()->create(['status' => 'draft']);
        $chapter = ProjectChapter::factory()->for($order->project)->create();
        $phase = WorkOrderPhase::factory()->for($order)->create();
        WorkOrderLine::factory()->for($order)->for($chapter, 'projectChapter')->for($phase, 'workOrderPhase')->create();
        WorkOrderEmployeeAssignment::factory()->for($order)->for($phase, 'workOrderPhase')->create();
        WorkOrderContractorAssignment::factory()->for($order)->for($phase, 'workOrderPhase')->create();

        return $order;
    }
}

class DenyDraftWorkOrderDeletion
{
    public function view(User $user, WorkOrder $order): bool
    {
        return true;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function update(User $user, WorkOrder $order): bool
    {
        return true;
    }

    public function delete(User $user, WorkOrder $order): bool
    {
        return false;
    }
}
