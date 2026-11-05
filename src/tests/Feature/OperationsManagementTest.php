<?php

namespace Tests\Feature;

use App\Filament\Schemas\OperationsForms;
use App\Models\ContractorAgreement;
use App\Models\ContractorCertificate;
use App\Models\InvoiceMaturity;
use App\Models\LaborCostRate;
use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\ProjectExpense;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceLine;
use App\Models\User;
use App\Models\WorkOrderEmployeeAssignment;
use App\Models\WorkTimeEntry;
use App\Models\WorkTimeReversal;
use App\Support\OperationsManager;
use App\Support\ProjectCosting;
use App\Support\ProjectEconomics;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class OperationsManagementTest extends TestCase
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

    public function test_all_operational_resources_render_their_forms_and_lists(): void
    {
        foreach (array_keys(OperationsForms::LABELS) as $model) {
            $resource = Filament::getModelResource('App\\Models\\'.$model);
            $this->assertNotNull($resource);
            Livewire::test($resource::getPages()['index']->getPage())->assertSuccessful();
            $resource::form(Schema::make()->model('App\\Models\\'.$model));
        }
    }

    public function test_contract_certification_reserves_the_contracted_amount(): void
    {
        $agreement = ContractorAgreement::factory()->confirmed()->create(['amount' => '10000']);
        $certificate = ContractorCertificate::factory()->create(['project_id' => $agreement->project_id, 'contractor_agreement_id' => $agreement->id, 'amount' => '4000']);
        app(OperationsManager::class)->post($certificate);
        $this->assertSame('posted', $certificate->fresh()->status);
        $this->expectException(ValidationException::class);
        ContractorCertificate::factory()->create(['project_id' => $agreement->project_id, 'contractor_agreement_id' => $agreement->id, 'amount' => '6000.01']);
    }

    public function test_purchase_invoice_and_partial_payment_keep_exact_outstanding_balance(): void
    {
        $invoice = PurchaseInvoice::factory()->create(['net_amount' => '1000', 'tax_amount' => '210', 'withholding_amount' => '150', 'retention_amount' => '50']);
        PurchaseInvoiceLine::factory()->create(['purchase_invoice_id' => $invoice->id, 'quantity' => '1', 'unit_price' => '1000', 'tax_percentage' => '21', 'withholding_percentage' => '15', 'retention_percentage' => '5']);
        $invoice->refresh();
        app(OperationsManager::class)->post($invoice);
        $this->assertSame('1010.00', $invoice->fresh()->payable_total);
        $payment = Payment::factory()->create(['project_id' => $invoice->project_id, 'party_id' => $invoice->supplier_id, 'purchase_invoice_id' => $invoice->id, 'direction' => 'payment', 'amount' => '500']);
        app(OperationsManager::class)->post($payment);
        $this->assertSame('posted', $payment->fresh()->status);
        $this->expectException(ValidationException::class);
        Payment::factory()->create(['project_id' => $invoice->project_id, 'party_id' => $invoice->supplier_id, 'purchase_invoice_id' => $invoice->id, 'direction' => 'payment', 'amount' => '510.01']);
    }

    public function test_advance_application_consumes_balance_without_recording_the_cash_twice(): void
    {
        $invoice = PurchaseInvoice::factory()->create();
        PurchaseInvoiceLine::factory()->create(['purchase_invoice_id' => $invoice->id, 'quantity' => '1', 'unit_price' => '100']);
        $invoice->refresh();
        app(OperationsManager::class)->post($invoice);
        $advance = Payment::factory()->create(['project_id' => $invoice->project_id, 'party_id' => $invoice->supplier_id, 'direction' => 'payment', 'amount' => '100']);
        app(OperationsManager::class)->post($advance);
        $application = PaymentApplication::factory()->create(['project_id' => $invoice->project_id, 'payment_id' => $advance->id, 'purchase_invoice_id' => $invoice->id, 'amount' => '60']);
        app(OperationsManager::class)->post($application);
        $this->assertSame('100.00', app(ProjectEconomics::class)->forProject($invoice->project)['paid']);
        $this->expectException(ValidationException::class);
        PaymentApplication::factory()->create(['project_id' => $invoice->project_id, 'payment_id' => $advance->id, 'purchase_invoice_id' => $invoice->id, 'amount' => '40.01']);
    }

    public function test_multiple_due_dates_cannot_exceed_the_invoice_total(): void
    {
        $invoice = PurchaseInvoice::factory()->create();
        PurchaseInvoiceLine::factory()->create(['purchase_invoice_id' => $invoice->id, 'quantity' => '1', 'unit_price' => '100']);
        $invoice->refresh();
        app(OperationsManager::class)->post($invoice);
        $maturity = InvoiceMaturity::factory()->create(['project_id' => $invoice->project_id, 'purchase_invoice_id' => $invoice->id, 'amount' => '50', 'operation_date' => today()->addMonth()]);
        app(OperationsManager::class)->post($maturity);
        $this->assertSame('posted', $maturity->fresh()->status);
        $this->expectException(ValidationException::class);
        InvoiceMaturity::factory()->create(['project_id' => $invoice->project_id, 'purchase_invoice_id' => $invoice->id, 'amount' => '50.01']);
    }

    public function test_time_reversal_keeps_original_and_allows_a_corrected_part_without_double_cost(): void
    {
        $entry = WorkTimeEntry::factory()->create();
        WorkOrderEmployeeAssignment::factory()->create(['employee_id' => $entry->employee_id, 'work_order_id' => $entry->work_order_id]);
        LaborCostRate::factory()->create(['employee_id' => $entry->employee_id, 'hourly_cost' => '20']);
        app(ProjectCosting::class)->postTime($entry);
        $reversal = WorkTimeReversal::factory()->create(['work_time_entry_id' => $entry->id, 'description' => 'Corrección de la imputación original']);
        app(OperationsManager::class)->post($reversal);
        $this->assertSame('0.00', app(ProjectCosting::class)->totals($entry->workOrder)['total']);
        $this->assertSame('posted', $entry->fresh()->status);
        $corrected = WorkTimeEntry::factory()->create($entry->only(['employee_id', 'work_order_id', 'work_order_phase_id', 'started_at', 'ended_at']));
        app(ProjectCosting::class)->postTime($corrected);
        $this->assertSame('20.00', app(ProjectCosting::class)->totals($entry->workOrder)['total']);
    }

    public function test_private_documents_and_csv_exports_respect_roles_and_project_ownership(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('project-documents/contract.pdf', 'private content');
        $project = Project::factory()->create();
        $document = ProjectDocument::factory()->create(['project_id' => $project->id, 'path' => 'project-documents/contract.pdf']);
        $this->get(route('project-documents.download', $document))->assertOk();
        $manager = User::factory()->create(['role' => 'manager']);
        $this->actingAs($manager);
        $this->get(route('project-documents.download', $document))->assertForbidden();
        $worker = User::factory()->create(['role' => 'worker']);
        $this->actingAs($worker);
        $this->get(route('operations.export', ['type' => 'payments']))->assertForbidden();
    }

    public function test_confirmed_cost_cannot_be_overwritten_by_an_old_form(): void
    {
        $expense = ProjectExpense::factory()->create();
        $this->assertDatabaseHas('audit_entries', ['subject_type' => ProjectExpense::class, 'subject_id' => $expense->id, 'event' => 'created']);
        $old = $expense->fresh();
        app(OperationsManager::class)->post($expense);
        $this->expectException(ValidationException::class);
        $old->update(['amount' => '1']);
    }
}
