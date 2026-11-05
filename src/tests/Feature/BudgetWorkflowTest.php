<?php

namespace Tests\Feature;

use App\Filament\Resources\Budgets\Pages\EditBudget;
use App\Filament\Resources\Budgets\Pages\ListBudgets;
use App\Models\Budget;
use App\Models\Project;
use App\Models\Unit;
use App\Models\User;
use App\Support\BudgetEditor;
use App\Support\BudgetWorkflow;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class BudgetWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();
        $this->actingAs(User::factory()->create());
    }

    public function test_one_save_keeps_name_terms_and_manual_lines_with_spanish_decimals(): void
    {
        $budget = Budget::factory()->create(['items' => []]);
        Livewire::test(EditBudget::class, ['record' => $budget->id])
            ->set('budgetName', 'Oferta completa')->set('commercialTerms.terms', 'Pago a treinta días')
            ->call('addManualLine')->set('manualLines.0.description', 'Instalación')
            ->set('manualLines.0.quantity', '2,5')->set('manualLines.0.price', '12,50')
            ->callAction('saveBudget')->assertHasNoActionErrors();
        $this->assertSame('Oferta completa', $budget->fresh()->name);
        $this->assertSame('Pago a treinta días', $budget->fresh()->terms);
        $this->assertSame('31.25', $budget->fresh()->total());
        $this->assertCount(1, $budget->fresh()->items);
    }

    public function test_stale_editor_keeps_changes_on_screen_without_overwriting_newer_document(): void
    {
        $budget = Budget::factory()->create(['items' => []]);
        $one = Livewire::test(EditBudget::class, ['record' => $budget->id]);
        $two = Livewire::test(EditBudget::class, ['record' => $budget->id]);
        $one->set('budgetName', 'Primera sesión')->callAction('saveBudget')->assertHasNoActionErrors();
        $two->set('budgetName', 'Segunda sesión')->callAction('saveBudget')->assertHasErrors('name')->assertSet('budgetName', 'Segunda sesión');
        $this->assertSame('Primera sesión', $budget->fresh()->name);
    }

    public function test_offer_can_start_from_a_project_without_a_work_order(): void
    {
        $project = Project::factory()->create();
        Livewire::test(ListBudgets::class)->callAction('newBudget', ['project_id' => $project->id, 'name' => 'Oferta previa'])->assertHasNoActionErrors();
        $this->assertNull(Budget::sole()->source_work_order_id);
        $this->assertSame($project->id, Budget::sole()->project_id);
    }

    public function test_accepted_offer_becomes_an_execution_order_once_with_accepted_prices(): void
    {
        $budget = Budget::factory()->create(['items' => []]);
        Unit::factory()->create(['symbol' => 'ud']);
        app(BudgetEditor::class)->saveManualLines($budget, [['description' => 'Servicio', 'unit' => 'ud', 'quantity' => '3', 'price' => '45', 'discount_percentage' => '10']]);
        $workflow = app(BudgetWorkflow::class);
        $workflow->send($budget);
        $workflow->accept($budget, 'Aceptado por cliente mediante correo de referencia 123');
        $order = $workflow->execute($budget);
        $again = $workflow->execute($budget);
        $this->assertSame($order->id, $again->id);
        $this->assertSame('accepted', $budget->fresh()->status);
        $this->assertSame('121.50', $budget->fresh()->total());
        $this->assertSame('10.0000', $order->workOrderLines()->sole()->discount_percentage);
        $this->assertSame('45.000000', $order->workOrderLines()->sole()->estimated_unit_sale_price);
        $this->assertSame('3.000000', $order->workOrderLines()->sole()->planned_quantity);
        $this->assertSame($budget->id, $order->accepted_budget_id);
        $this->assertSame(1, $order->workOrderPhases()->count());

        $revision = $workflow->revise($budget);
        $this->assertSame('draft', $revision->status);
        $this->assertSame($budget->id, $revision->revises_id);
        $this->assertSame(2, $revision->revision);
        $this->assertSame('accepted', $budget->fresh()->status);
    }

    public function test_accepted_offer_cannot_be_modified_directly(): void
    {
        $budget = Budget::factory()->create(['items' => []]);
        app(BudgetEditor::class)->saveManualLines($budget, [['description' => 'Servicio', 'unit' => 'ud', 'quantity' => '1', 'price' => '10']]);
        app(BudgetWorkflow::class)->send($budget);
        app(BudgetWorkflow::class)->accept($budget, 'Aceptado por el cliente');
        $this->expectException(ValidationException::class);
        $budget->refresh()->update(['name' => 'Alteración histórica']);
    }
}
