<?php

namespace App\Support;

use App\Models\Budget;
use App\Models\ContractorAgreement;
use App\Models\ContractorCertificate;
use App\Models\MaterialConsumption;
use App\Models\Payment;
use App\Models\PaymentReversal;
use App\Models\Project;
use App\Models\ProjectExpense;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\SalesInvoice;
use App\Models\WorkTimeEntry;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

final class ProjectEconomics
{
    public static function sum(Builder|Relation $query, string $field): string
    {
        $total = BigDecimal::of(0);
        foreach ($query->pluck($field) as $amount) {
            $total = $total->plus((string) $amount);
        }

        return InvoiceCalculator::money($total);
    }

    /** @return array<string, string> */
    public function forProject(Project $project): array
    {
        $accepted = BigDecimal::of(0);
        foreach (Budget::where('project_id', $project->id)->where('status', 'accepted')->get() as $budget) {
            $accepted = $accepted->plus($budget->total());
        }
        $materials = self::sum(MaterialConsumption::whereHas('workOrder', fn (Builder $q) => $q->where('project_id', $project->id))->where('status', 'posted'), 'total_cost');
        $labor = self::sum(WorkTimeEntry::whereHas('workOrder', fn (Builder $q) => $q->where('project_id', $project->id))->where('status', 'posted')->whereDoesntHave('reversals', fn (Builder $q) => $q->where('status', 'posted')), 'total_cost');
        $contractors = self::sum(ContractorCertificate::where('project_id', $project->id)->where('status', 'posted'), 'amount');
        $expenses = self::sum(ProjectExpense::where('project_id', $project->id)->where('status', 'posted'), 'amount');
        $directPurchases = self::sum(PurchaseInvoice::where('project_id', $project->id)->where('status', 'posted')->whereNull('purchase_delivery_note_id')->whereNull('contractor_agreement_id'), 'net_amount');
        $actual = BigDecimal::of($materials)->plus($labor)->plus($contractors)->plus($expenses)->plus($directPurchases);
        $committed = BigDecimal::of(self::sum(ContractorAgreement::where('project_id', $project->id)->where('status', 'posted'), 'amount'))
            ->plus(self::sum(PurchaseOrder::where('project_id', $project->id)->where('status', 'posted'), 'amount'));
        $invoiced = self::sum(SalesInvoice::whereHas('workOrder', fn (Builder $q) => $q->where('project_id', $project->id))->where('status', 'issued'), 'payable_total');
        $purchases = self::sum(PurchaseInvoice::where('project_id', $project->id)->where('status', 'posted'), 'payable_total');
        $received = self::sum(Payment::where('project_id', $project->id)->where('status', 'posted')->where('direction', 'receipt'), 'amount');
        $paid = self::sum(Payment::where('project_id', $project->id)->where('status', 'posted')->where('direction', 'payment'), 'amount');

        $received = InvoiceCalculator::money(BigDecimal::of($received)->minus(self::sum(PaymentReversal::where('project_id', $project->id)->where('status', 'posted')->whereHas('payment', fn (Builder $q) => $q->where('direction', 'receipt')), 'amount')));
        $paid = InvoiceCalculator::money(BigDecimal::of($paid)->minus(self::sum(PaymentReversal::where('project_id', $project->id)->where('status', 'posted')->whereHas('payment', fn (Builder $q) => $q->where('direction', 'payment')), 'amount')));

        return [
            'accepted' => InvoiceCalculator::money($accepted), 'materials' => $materials, 'labor' => $labor,
            'contractors' => $contractors, 'expenses' => $expenses, 'direct_purchases' => $directPurchases,
            'actual' => InvoiceCalculator::money($actual), 'committed' => InvoiceCalculator::money($committed),
            'invoiced' => $invoiced, 'purchase_payable' => $purchases, 'received' => $received, 'paid' => $paid,
            'cash' => InvoiceCalculator::money(BigDecimal::of($received)->minus($paid)),
            'sale_less_actual' => InvoiceCalculator::money($accepted->minus($actual)),
        ];
    }
}
