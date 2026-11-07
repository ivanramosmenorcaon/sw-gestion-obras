<?php

namespace App\Support;

use App\Models\ContractorAgreement;
use App\Models\ContractorCertificate;
use App\Models\Employee;
use App\Models\EmployeeAbsence;
use App\Models\InvoiceMaturity;
use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\PaymentReversal;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectDocument;
use App\Models\PurchaseDeliveryNote;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\SalesInvoice;
use App\Models\WorkProgress;
use App\Models\WorkTimeEntry;
use App\Models\WorkTimeReversal;
use Brick\Math\BigDecimal;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class OperationsManager
{
    public function persist(Model $record, Closure $save): bool
    {
        return DB::transaction(function () use ($record, $save): bool {
            if ($record->project_id) {
                Project::whereKey($record->project_id)->lockForUpdate()->firstOrFail();
            }
            if ($record instanceof Payment || $record instanceof PaymentApplication || $record instanceof InvoiceMaturity) {
                $record->sales_invoice_id ? SalesInvoice::whereKey($record->sales_invoice_id)->lockForUpdate()->firstOrFail() : null;
                $record->purchase_invoice_id ? PurchaseInvoice::whereKey($record->purchase_invoice_id)->lockForUpdate()->firstOrFail() : null;
            }
            if ($record->payment_id) {
                Payment::whereKey($record->payment_id)->lockForUpdate()->firstOrFail();
            }
            if ($record instanceof EmployeeAbsence) {
                Employee::whereKey($record->employee_id)->lockForUpdate()->firstOrFail();
            }
            if ($record instanceof WorkTimeReversal) {
                WorkTimeEntry::whereKey($record->work_time_entry_id)->lockForUpdate()->firstOrFail();
            }
            if ($record->exists) {
                $stored = $record->newQuery()->whereKey($record->id)->lockForUpdate()->firstOrFail();
                $absenceMetadataOnly = $record instanceof EmployeeAbsence && $stored->status === 'posted' && $record->status === 'posted'
                    && ! $record->isDirty(['employee_id', 'starts_on', 'ends_on', 'status']);
                if ($stored->status !== 'draft' && ! $absenceMetadataOnly) {
                    $this->fail('status', 'El registro ya está confirmado o cancelado. No se puede sobrescribir.');
                }
            }

            return $save();
        }, 3);
    }

    public function validate(Model $record): void
    {
        $rules = [
            'reference' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:10000'],
            'status' => ['required', Rule::in(['draft', 'posted', 'cancelled'])],
        ];
        if (in_array($record->getTable(), ['contractor_agreements', 'contractor_certificates', 'purchase_orders'], true)) {
            $rules['reference'][] = Rule::unique($record->getTable(), 'reference')->ignore($record->id);
        }
        if ($record instanceof PurchaseInvoice) {
            $rules['reference'][] = Rule::unique('purchase_invoices', 'reference')->where('supplier_id', $record->supplier_id)->ignore($record->id);
        }
        if ($record instanceof ProjectDocument) {
            $rules['reference'][] = Rule::unique('project_documents', 'reference')->where('project_id', $record->project_id)->where('version', $record->version)->ignore($record->id);
        }
        foreach ($record->getFillable() as $field) {
            if (in_array($field, ['project_id', 'employee_id', 'contractor_id', 'supplier_id', 'party_id', 'work_order_line_id', 'work_time_entry_id', 'amount', 'net_amount', 'operation_date'], true)) {
                $rules[$field] = ['required'];
            }
        }
        foreach (['project_id' => 'projects', 'employee_id' => 'employees', 'contractor_id' => 'parties', 'supplier_id' => 'parties', 'party_id' => 'parties', 'contractor_agreement_id' => 'contractor_agreements', 'work_order_line_id' => 'work_order_lines', 'work_time_entry_id' => 'work_time_entries'] as $field => $table) {
            if (array_key_exists($field, $record->getAttributes())) {
                $rules[$field] = [isset($rules[$field]) ? 'required' : 'nullable', 'integer', 'exists:'.$table.',id'];
            }
        }
        foreach (['amount', 'net_amount', 'tax_amount', 'withholding_amount', 'retention_amount'] as $field) {
            if (array_key_exists($field, $record->getAttributes())) {
                $rules[$field] = ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'];
            }
        }
        foreach (['operation_date', 'starts_on', 'ends_on', 'due_date', 'valid_until'] as $field) {
            if ($record->getAttribute($field)) {
                $rules[$field] = ['date'];
            }
        }
        validator($record->getAttributes(), $rules)->validate();
        if ($record->project_id && auth()->check() && ! OperationalAccess::allows(Project::class, 'view', $record->project)) {
            abort(403);
        }
        if ($record->project_id && $record->project->currency_code !== 'EUR') {
            $this->fail('project_id', 'La operativa económica actual requiere una obra en EUR.');
        }
        if ($record->getAttribute('work_order_phase_id') && $record->workOrderPhase->workOrder->project_id !== $record->project_id) {
            $this->fail('work_order_phase_id', 'La fase debe pertenecer a la obra.');
        }
        if ($record->project_id && in_array($record->project->status, ['completed', 'cancelled'], true) && ! $record instanceof Payment && ! $record instanceof PaymentApplication && ! $record instanceof InvoiceMaturity && ! $record instanceof PaymentReversal && ! $record instanceof ProjectDocument && ! $record instanceof ProjectActivity) {
            $this->fail('project_id', 'La obra está cerrada o cancelada. Reábrala con autorización antes de imputar operaciones.');
        }
        if ($record->exists && $record->isDirty('project_id')) {
            $this->fail('project_id', 'La obra no puede cambiar después de crear el registro.');
        }
        if (! $record instanceof InvoiceMaturity && $record->status === 'posted' && ($record->operation_date && $record->operation_date->toDateString() > today('Europe/Madrid')->toDateString())) {
            $this->fail('operation_date', 'No puede confirmar una operación futura.');
        }
        if ($record->starts_on && $record->ends_on && $record->ends_on->lt($record->starts_on)) {
            $this->fail('ends_on', 'El fin debe ser igual o posterior al inicio.');
        }
        if ($record instanceof EmployeeAbsence) {
            validator($record->getAttributes(), ['starts_on' => ['required', 'date'], 'ends_on' => ['required', 'date', 'after_or_equal:starts_on']])->validate();
        }
        if ($record instanceof EmployeeAbsence && $record->status === 'posted') {
            if (EmployeeAbsence::where('employee_id', $record->employee_id)->whereKeyNot($record->id ?? 0)->where('status', 'posted')->whereDate('starts_on', '<=', $record->ends_on)->whereDate('ends_on', '>=', $record->starts_on)->exists()) {
                $this->fail('starts_on', 'La ausencia se solapa con otra aprobada.');
            }
        }
        if ($record instanceof ContractorAgreement) {
            validator($record->getAttributes(), ['retention_percentage' => ['required', 'numeric', 'between:0,100']])->validate();
            if (! $record->contractor->partyRoles()->where('role', 'subcontractor')->exists()) {
                $this->fail('contractor_id', 'El contacto debe tener el rol Subcontratista.');
            }
        }
        if ($record instanceof ContractorCertificate) {
            $agreement = $record->contractorAgreement;
            if ($agreement->project_id !== $record->project_id || $agreement->status !== 'posted') {
                $this->fail('contractor_agreement_id', 'El contrato debe estar confirmado y pertenecer a la obra.');
            }
            $used = ContractorCertificate::where('contractor_agreement_id', $agreement->id)->whereKeyNot($record->id ?? 0)->where('status', '!=', 'cancelled')->sum('amount');
            if (BigDecimal::of((string) $used)->plus($record->amount)->compareTo($agreement->amount) > 0) {
                $this->fail('amount', 'Supera el importe contratado. Apruebe un contrato adicional para los extras.');
            }
        }
        if ($record instanceof PurchaseOrder || $record instanceof PurchaseInvoice) {
            if ($record->status === 'posted' && (! $record->exists || ! $record->lines()->exists())) {
                $this->fail('lines', 'Añada y guarde al menos una línea antes de confirmar.');
            }
            if ($record->exists && $record->lines()->exists()) {
                if ($record instanceof PurchaseOrder) {
                    $record->amount = ProjectEconomics::sum($record->lines(), 'net_amount');
                } else {
                    foreach (['net_amount', 'tax_amount', 'withholding_amount', 'retention_amount'] as $field) {
                        $record->$field = ProjectEconomics::sum($record->lines(), $field);
                    }
                }
            }
            if (! $record->supplier->partyRoles()->whereIn('role', ['supplier', 'subcontractor'])->exists()) {
                $this->fail('supplier_id', 'El contacto debe ser proveedor o subcontratista.');
            }
        }
        if ($record instanceof PurchaseInvoice) {
            foreach (['purchase_order_id' => PurchaseOrder::class, 'purchase_delivery_note_id' => PurchaseDeliveryNote::class, 'contractor_agreement_id' => ContractorAgreement::class] as $key => $model) {
                if (! $record->$key) {
                    continue;
                }
                $owner = $model::findOrFail($record->$key);
                if (($owner->project_id && $owner->project_id !== $record->project_id) || ($owner->supplier_id ?? $owner->contractor_id) !== $record->supplier_id) {
                    $this->fail($key, 'La obra y el proveedor deben coincidir con el documento de origen.');
                }
            }
            if ($record->purchase_delivery_note_id && $record->contractor_agreement_id) {
                $this->fail('contractor_agreement_id', 'Seleccione recepción de materiales o contrato de subcontrata, no ambos.');
            }
            $total = BigDecimal::of($record->net_amount)->plus($record->tax_amount)->minus($record->withholding_amount)->minus($record->retention_amount);
            if ($total->isNegative()) {
                $this->fail('net_amount', 'El importe a pagar no puede ser negativo.');
            }
            $record->payable_total = InvoiceCalculator::money($total);
        }
        if ($record instanceof Payment) {
            validator($record->getAttributes(), ['direction' => ['required', Rule::in(['receipt', 'payment'])], 'method' => ['required', Rule::in(['transfer', 'cash', 'card', 'other'])], 'amount' => ['required', 'numeric', 'gt:0']])->validate();
            if ($record->sales_invoice_id && $record->purchase_invoice_id) {
                $this->fail('sales_invoice_id', 'Un movimiento solo puede aplicarse a una factura.');
            }
            $invoice = $record->sales_invoice_id ? SalesInvoice::findOrFail($record->sales_invoice_id) : ($record->purchase_invoice_id ? PurchaseInvoice::findOrFail($record->purchase_invoice_id) : null);
            if ($invoice) {
                $sales = $invoice instanceof SalesInvoice;
                $projectId = $sales ? $invoice->workOrder?->project_id : $invoice->project_id;
                if (($sales ? $invoice->status !== 'issued' : $invoice->status !== 'posted') || $projectId !== $record->project_id || ($sales ? $invoice->customer_id : $invoice->supplier_id) !== $record->party_id || $record->direction !== ($sales ? 'receipt' : 'payment')) {
                    $this->fail('party_id', 'El movimiento debe coincidir con una factura confirmada de esa obra, tercero y sentido.');
                }
                $field = $sales ? 'sales_invoice_id' : 'purchase_invoice_id';
                $paid = $this->directPayments($field, $invoice->id, $record->id)->plus((string) PaymentApplication::where($field, $invoice->id)->where('status', '!=', 'cancelled')->sum('amount'));
                if (BigDecimal::of((string) $paid)->plus($record->amount)->compareTo($invoice->payable_total) > 0) {
                    $this->fail('amount', 'El movimiento supera el saldo pendiente, incluidos otros borradores.');
                }
            }
        }
        if ($record instanceof PaymentApplication || $record instanceof PaymentReversal || $record instanceof InvoiceMaturity) {
            validator($record->getAttributes(), ['amount' => ['required', 'numeric', 'gt:0'], 'payment_id' => [($record instanceof InvoiceMaturity) ? 'nullable' : 'required', 'exists:payments,id']])->validate();
        }
        if ($record instanceof PaymentReversal) {
            $payment = $record->payment;
            $reserved = PaymentReversal::where('payment_id', $payment->id)->whereKeyNot($record->id ?? 0)->where('status', '!=', 'cancelled')->sum('amount');
            $applications = PaymentApplication::where('payment_id', $payment->id)->where('status', '!=', 'cancelled')->sum('amount');
            if ($payment->status !== 'posted' || $payment->project_id !== $record->project_id || BigDecimal::of((string) $reserved)->plus((string) $applications)->plus($record->amount)->compareTo($payment->amount) > 0) {
                $this->fail('amount', 'La devolución supera el importe no aplicado del movimiento confirmado.');
            }
        }
        if ($record instanceof PaymentApplication || $record instanceof InvoiceMaturity) {
            if ((bool) $record->sales_invoice_id === (bool) $record->purchase_invoice_id) {
                $this->fail('sales_invoice_id', 'Seleccione exactamente una factura.');
            }
            $sales = (bool) $record->sales_invoice_id;
            $invoice = $sales ? SalesInvoice::findOrFail($record->sales_invoice_id) : PurchaseInvoice::findOrFail($record->purchase_invoice_id);
            $field = $sales ? 'sales_invoice_id' : 'purchase_invoice_id';
            $projectId = $sales ? $invoice->workOrder?->project_id : $invoice->project_id;
            if ($projectId !== $record->project_id || $invoice->status !== ($sales ? 'issued' : 'posted')) {
                $this->fail($field, 'La factura debe estar confirmada y pertenecer a la obra.');
            }
            if ($record instanceof InvoiceMaturity) {
                $planned = InvoiceMaturity::where($field, $invoice->id)->whereKeyNot($record->id ?? 0)->where('status', '!=', 'cancelled')->sum('amount');
                if (BigDecimal::of((string) $planned)->plus($record->amount)->compareTo($invoice->payable_total) > 0) {
                    $this->fail('amount', 'Los plazos superan el importe de la factura.');
                }
            } else {
                $payment = $record->payment;
                $applied = PaymentApplication::where('payment_id', $payment->id)->whereKeyNot($record->id ?? 0)->where('status', '!=', 'cancelled')->sum('amount');
                $refunded = PaymentReversal::where('payment_id', $payment->id)->where('status', '!=', 'cancelled')->sum('amount');
                if ($payment->status !== 'posted' || $payment->sales_invoice_id || $payment->purchase_invoice_id || $payment->project_id !== $record->project_id || $payment->party_id !== ($sales ? $invoice->customer_id : $invoice->supplier_id) || $payment->direction !== ($sales ? 'receipt' : 'payment') || BigDecimal::of((string) $applied)->plus((string) $refunded)->plus($record->amount)->compareTo($payment->amount) > 0) {
                    $this->fail('payment_id', 'El anticipo no es compatible o no tiene saldo suficiente.');
                }
                $invoiceApplications = PaymentApplication::where($field, $invoice->id)->whereKeyNot($record->id ?? 0)->where('status', '!=', 'cancelled')->sum('amount');
                $direct = $this->directPayments($field, $invoice->id);
                if (BigDecimal::of((string) $invoiceApplications)->plus((string) $direct)->plus($record->amount)->compareTo($invoice->payable_total) > 0) {
                    $this->fail('amount', 'La aplicación supera el saldo de la factura.');
                }
            }
        }
        if ($record instanceof WorkProgress) {
            if (in_array($record->workOrderLine->workOrder->status, ['completed', 'cancelled'], true)) {
                $this->fail('work_order_line_id', 'La intervención está cerrada o cancelada. Reábrala antes de imputar avance.');
            }
            if ($record->workOrderLine->workOrder->purpose === 'measurement') {
                $this->fail('work_order_line_id', 'Seleccione una partida encargada en una intervención, no una medición de presupuesto.');
            }
            validator($record->getAttributes(), ['quantity' => ['required', 'numeric', 'decimal:0,6', 'gt:0']])->validate();
            if ($record->workOrderLine->project_id !== $record->project_id) {
                $this->fail('work_order_line_id', 'La partida debe pertenecer a la obra.');
            }
            $executed = WorkProgress::where('work_order_line_id', $record->work_order_line_id)->whereKeyNot($record->id ?? 0)->where('status', '!=', 'cancelled')->sum('quantity');
            if (BigDecimal::of((string) $executed)->plus($record->quantity)->compareTo($record->workOrderLine->planned_quantity) > 0) {
                $this->fail('quantity', 'Supera la cantidad prevista. Apruebe el extra antes de ampliarla.');
            }
        }
        if ($record instanceof WorkTimeReversal) {
            if ($record->workTimeEntry->status !== 'posted' || ! filled($record->description)) {
                $this->fail('work_time_entry_id', 'Seleccione un parte confirmado e indique el motivo de la reversión.');
            }
            $entry = $record->workTimeEntry;
            if (auth()->check() && ! OperationalAccess::scope(WorkTimeEntry::query())->whereKey($entry->id)->exists()) {
                abort(403);
            }
        }
        if ($record instanceof ProjectDocument) {
            validator($record->getAttributes(), ['path' => ['required', 'string', 'starts_with:project-documents/'], 'version' => ['required', 'integer', 'min:1']])->validate();
            if (! Storage::disk('local')->exists($record->path)) {
                $this->fail('path', 'El archivo privado no está disponible.');
            }
        }
    }

    public function post(Model $record): void
    {
        if (! OperationalAccess::allows($record::class, 'update', $record)) {
            abort(403);
        }
        $record->forceFill(['status' => 'posted'])->save();
    }

    public function cancel(Model $record): void
    {
        if (! OperationalAccess::allows($record::class, 'update', $record)) {
            abort(403);
        }
        $record->forceFill(['status' => 'cancelled'])->save();
    }

    private function directPayments(string $field, int $invoiceId, ?int $except = null): BigDecimal
    {
        $total = BigDecimal::of(0);
        foreach (Payment::where($field, $invoiceId)->when($except, fn ($query) => $query->whereKeyNot($except))->where('status', '!=', 'cancelled')->get() as $payment) {
            $returned = PaymentReversal::where('payment_id', $payment->id)->where('status', 'posted')->sum('amount');
            $total = $total->plus($payment->amount)->minus((string) $returned);
        }

        return $total;
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
