<?php

namespace App\Support;

use App\Models\BillingRecord;
use App\Models\CompanySetting;
use App\Models\InvoiceSeries;
use App\Models\Party;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLine;
use App\Models\WorkOrder;
use App\Models\WorkOrderLine;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class SalesBilling
{
    public function assertDraft(SalesInvoice $invoice): void
    {
        if ($invoice->status !== 'draft') {
            throw ValidationException::withMessages(['status' => __('billing.messages.immutable')]);
        }
    }

    public function validateHeader(SalesInvoice $invoice): void
    {
        Validator::make($invoice->getAttributes(), [
            'customer_id' => ['required', 'exists:parties,id'], 'invoice_series_id' => ['required', 'exists:invoice_series,id'],
            'work_order_id' => ['nullable', 'exists:work_orders,id'], 'issue_date' => ['required', 'date'],
            'operation_date' => ['required', 'date'], 'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'description' => ['required', 'string', 'max:500'], 'currency_code' => ['in:EUR'],
            'status' => ['in:draft,issued,cancelled'], 'invoice_type' => ['in:F1,R1,R2,R3,R4'],
        ], [], __('billing.fields'))->validate();
        if ($invoice->status === 'cancelled') {
            return;
        }
        $customer = Party::findOrFail($invoice->customer_id);
        if (! $invoice->rectifies_invoice_id && (! $customer->is_active || ! $customer->partyRoles()->where('role', 'customer')->exists())) {
            throw ValidationException::withMessages(['customer_id' => __('billing.messages.customer')]);
        }
        if (! $invoice->rectifies_invoice_id && $invoice->work_order_id && WorkOrder::findOrFail($invoice->work_order_id)->project->customer_id !== $customer->id) {
            throw ValidationException::withMessages(['work_order_id' => __('billing.messages.owner')]);
        }
        $series = InvoiceSeries::findOrFail($invoice->invoice_series_id);
        if (! $series->is_active || ($series->kind === 'rectifying') !== (bool) $invoice->rectifies_invoice_id) {
            throw ValidationException::withMessages(['invoice_series_id' => __('billing.messages.series')]);
        }
        if ($invoice->exists && $invoice->lines()->exists() && $invoice->isDirty(['customer_id', 'work_order_id', 'invoice_series_id'])) {
            throw ValidationException::withMessages(['customer_id' => __('billing.messages.header_locked')]);
        }
        if ($invoice->rectifies_invoice_id && ($invoice->rectifiesInvoice->status !== 'issued' || $invoice->rectifiesInvoice->customer_id !== $invoice->customer_id || $invoice->invoice_type === 'F1')) {
            throw ValidationException::withMessages(['customer_id' => __('billing.messages.owner')]);
        }
    }

    public function validateLine(SalesInvoiceLine $line): void
    {
        $invoice = $line->invoice()->firstOrFail();
        $this->assertDraft($invoice);
        Validator::make($line->getAttributes(), [
            'description' => ['required', 'string', 'max:500'], 'unit_label' => ['nullable', 'string', 'max:30'],
            'quantity' => ['required', 'numeric', 'decimal:0,6', $invoice->rectifies_invoice_id ? 'lt:0' : 'gt:0'],
            'unit_price' => ['required', 'numeric', 'decimal:0,6', 'min:0', 'max:999999999999'],
            'discount_percentage' => ['required', 'numeric', 'between:0,100'], 'position' => ['required', 'integer', 'min:1'],
            'tax_profile_id' => ['required', 'exists:tax_profiles,id'], 'withholding_rate_id' => ['nullable', 'exists:tax_rates,id'],
            'product_id' => ['nullable', 'exists:products,id'],
        ], [], __('billing.fields'))->validate();
        if ($line->exists && $line->isDirty(['sales_invoice_id', 'work_order_line_id', 'rectifies_line_id'])) {
            throw ValidationException::withMessages(['quantity' => __('billing.messages.owner')]);
        }
        if ($invoice->rectifies_invoice_id) {
            $original = $line->rectifiesLine()->lockForUpdate()->first();
            if (! $original || $original->sales_invoice_id !== $invoice->rectifies_invoice_id) {
                throw ValidationException::withMessages(['quantity' => __('billing.messages.owner')]);
            }
            foreach (['product_id', 'work_order_line_id', 'tax_profile_id', 'withholding_rate_id', 'unit_price', 'discount_percentage'] as $field) {
                if ((string) $line->$field !== (string) $original->$field) {
                    throw ValidationException::withMessages([$field => __('billing.messages.credit_fields')]);
                }
            }
            $reserved = SalesInvoiceLine::where('rectifies_line_id', $original->id)->whereKeyNot($line->id ?? 0)->whereHas('invoice', fn ($q) => $q->whereIn('status', ['draft', 'issued']))->pluck('quantity')->reduce(fn (BigDecimal $sum, string $qty): BigDecimal => $sum->plus($qty), BigDecimal::of('0'));
            if (BigDecimal::of($original->quantity)->plus($reserved)->plus($line->quantity)->compareTo('0') < 0) {
                throw ValidationException::withMessages(['quantity' => __('billing.messages.overbilled')]);
            }

            return;
        }
        if ($line->rectifies_line_id) {
            throw ValidationException::withMessages(['quantity' => __('billing.messages.owner')]);
        }
        if ($line->work_order_line_id) {
            $source = $line->workOrderLine()->firstOrFail();
            WorkOrder::whereKey($source->work_order_id)->lockForUpdate()->firstOrFail();
            if ($source->work_order_id !== $invoice->work_order_id || $source->project->customer_id !== $invoice->customer_id || BigDecimal::of($line->quantity)->compareTo($this->remaining($source->id, $line->id)) > 0) {
                throw ValidationException::withMessages(['quantity' => __('billing.messages.overbilled')]);
            }
        }
    }

    public function remaining(int $sourceId, ?int $excludingLine = null): string
    {
        $source = WorkOrderLine::findOrFail($sourceId);
        $used = SalesInvoiceLine::where('work_order_line_id', $sourceId)->when($excludingLine, fn ($q) => $q->whereKeyNot($excludingLine))
            ->whereHas('invoice', fn ($q) => $q->where('status', 'issued')->orWhere(fn ($draft) => $draft->where('status', 'draft')->whereNull('rectifies_invoice_id')))
            ->pluck('quantity')->reduce(fn (BigDecimal $sum, string $qty): BigDecimal => $sum->plus($qty), BigDecimal::of('0'));

        return (string) BigDecimal::of($source->planned_quantity)->minus($used);
    }

    /** @param array<int, int|string> $phaseIds */
    public function fromWorkOrder(WorkOrder $order, int $seriesId, array $phaseIds, string $percentage = '100'): SalesInvoice
    {
        Validator::make(['percentage' => $percentage, 'phases' => $phaseIds], ['percentage' => ['required', 'numeric', 'gt:0', 'max:100'], 'phases' => ['required', 'array', 'min:1']])->validate();

        return DB::transaction(function () use ($order, $seriesId, $phaseIds, $percentage): SalesInvoice {
            $order = WorkOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->project->currency_code !== 'EUR') {
                throw ValidationException::withMessages(['work_order_id' => __('billing.messages.currency')]);
            }
            if ($order->workOrderPhases()->whereIn('id', $phaseIds)->count() !== count(array_unique($phaseIds))) {
                throw ValidationException::withMessages(['phase_ids' => __('billing.messages.owner')]);
            }
            $invoice = SalesInvoice::create(['customer_id' => $order->project->customer_id, 'invoice_series_id' => $seriesId, 'work_order_id' => $order->id,
                'issue_date' => today('Europe/Madrid')->toDateString(), 'operation_date' => today('Europe/Madrid')->toDateString(), 'description' => __('billing.order_description', ['reference' => $order->reference])]);
            $company = CompanySetting::where('scope', 'company')->first();
            foreach ($order->workOrderLines()->whereIn('work_order_phase_id', $phaseIds)->with('product')->orderBy('position')->get() as $source) {
                $available = BigDecimal::of($this->remaining($source->id));
                if ($available->compareTo('0') <= 0) {
                    continue;
                }
                $quantity = $available->multipliedBy($percentage)->dividedBy('100', 6, RoundingMode::Down);
                if ($quantity->compareTo('0') <= 0) {
                    continue;
                }
                $profile = $source->product?->sales_tax_profile_id ?? $invoice->customer->sales_tax_profile_id ?? $company?->sales_tax_profile_id;
                if (! $profile) {
                    throw ValidationException::withMessages(['tax_profile_id' => __('billing.messages.no_default')]);
                }
                $line = new SalesInvoiceLine(['sales_invoice_id' => $invoice->id, 'product_id' => $source->product_id, 'position' => $source->position,
                    'description' => $source->description, 'unit_label' => $source->unit_label, 'quantity' => (string) $quantity, 'unit_price' => $source->estimated_unit_sale_price,
                    'tax_profile_id' => $profile,
                    'withholding_rate_id' => $source->product?->sales_withholding_rate_id ?? $invoice->customer->sales_withholding_rate_id ?? $company?->sales_withholding_rate_id]);
                $line->work_order_line_id = $source->id;
                $line->save();
            }
            if (! $invoice->lines()->exists()) {
                throw ValidationException::withMessages(['phase_ids' => __('billing.messages.nothing_pending')]);
            }

            return $invoice;
        }, 3);
    }

    public function cancel(SalesInvoice $invoice): void
    {
        DB::transaction(function () use ($invoice): void {
            $invoice = SalesInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $this->assertDraft($invoice);
            $invoice->status = 'cancelled';
            $invoice->save();
        });
    }

    public function issue(SalesInvoice $invoice): SalesInvoice
    {
        return DB::transaction(function () use ($invoice): SalesInvoice {
            $invoice = SalesInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $this->assertDraft($invoice);
            $series = InvoiceSeries::whereKey($invoice->invoice_series_id)->lockForUpdate()->firstOrFail();
            if (! $series->is_active || $series->fiscal_year !== $invoice->issue_date->year || ($series->kind === 'rectifying') !== (bool) $invoice->rectifies_invoice_id) {
                throw ValidationException::withMessages(['invoice_series_id' => __('billing.messages.series')]);
            }
            if ($series->environment === 'production') {
                throw ValidationException::withMessages(['invoice_series_id' => __('billing.messages.production_blocked')]);
            }
            if ($invoice->issue_date->toDateString() > today('Europe/Madrid')->toDateString() || $invoice->operation_date->gt($invoice->issue_date)) {
                throw ValidationException::withMessages(['issue_date' => __('billing.messages.dates')]);
            }
            $lastDate = $series->invoices()->where('status', 'issued')->max('issue_date');
            if ($lastDate && $invoice->issue_date->lt($lastDate)) {
                throw ValidationException::withMessages(['issue_date' => __('billing.messages.chronology')]);
            }
            if (! $invoice->lines()->exists()) {
                throw ValidationException::withMessages(['lines' => __('billing.messages.empty')]);
            }
            foreach ($invoice->lines as $line) {
                $this->validateLine($line);
            }
            $company = CompanySetting::where('scope', 'company')->firstOrFail();
            FiscalValidator::validate($company);
            if ($company->tax_identification_type !== 'nif' || strlen($company->tax_identification_number ?? '') !== 9 || $company->country_code !== 'ES') {
                throw ValidationException::withMessages(['issuer_snapshot' => __('billing.messages.issuer')]);
            }
            $originalRecipient = $invoice->rectifies_invoice_id ? $invoice->rectifiesInvoice->recipient_snapshot : null;
            $address = $invoice->customer->partyAddresses()->where('is_active', true)->where('is_fiscal', true)->get();
            if (! $originalRecipient && $address->count() !== 1) {
                throw ValidationException::withMessages(['customer_id' => __('billing.messages.address')]);
            }
            foreach (array_filter([$company, $originalRecipient ? null : $address->first()]) as $fiscalAddress) {
                foreach (['address_line_1', 'city', 'postal_code', 'country_code'] as $field) {
                    if (blank($fiscalAddress->$field)) {
                        throw ValidationException::withMessages(['customer_id' => __('billing.messages.address')]);
                    }
                }
            }
            $calculation = app(InvoiceCalculator::class)->calculate($invoice, true);
            if (BigDecimal::of($calculation['totals']['gross_total'])->abs()->compareTo('999999999999.99') > 0) {
                throw ValidationException::withMessages(['lines' => __('billing.messages.amount_limit')]);
            }
            $invoice->forceFill($calculation['totals'] + ['tax_summary' => $calculation['groups'], 'issuer_snapshot' => $company->only(['legal_name', 'tax_identification_number', 'address_line_1', 'address_line_2', 'city', 'province', 'postal_code', 'country_code']),
                'recipient_snapshot' => $originalRecipient ?? ['identity' => FiscalIdentity::recipient($invoice->customer), 'address' => $address->first()->only(['address_line_1', 'address_line_2', 'city', 'province', 'postal_code', 'country_code'])],
                'sequence_number' => $series->next_number, 'number' => $series->code.'/'.$series->fiscal_year.'/'.str_pad((string) $series->next_number, 6, '0', STR_PAD_LEFT), 'environment' => $series->environment]);
            foreach ($calculation['lines'] as $id => $values) {
                SalesInvoiceLine::whereKey($id)->update(array_replace($values, ['fiscal_snapshot' => json_encode($values['fiscal_snapshot'], JSON_THROW_ON_ERROR)]));
            }
            $series->next_number++;
            $series->save();
            DB::table('billing_chains')->insertOrIgnore(['environment' => $series->environment, 'created_at' => now(), 'updated_at' => now()]);
            $chain = DB::table('billing_chains')->where('environment', $series->environment)->lockForUpdate()->first();
            $previous = $chain->last_record_id ? BillingRecord::findOrFail($chain->last_record_id) : null;
            if ($previous && $previous->invoice->issuer_snapshot['tax_identification_number'] !== $company->tax_identification_number) {
                throw ValidationException::withMessages(['issuer_snapshot' => __('billing.messages.issuer_changed')]);
            }
            $generatedAt = now('Europe/Madrid')->toIso8601String();
            $system = app(VerifactuXml::class)->system();
            if ($previous) {
                foreach (['NIF', 'IdSistemaInformatico', 'NumeroInstalacion'] as $field) {
                    if ($previous->system_snapshot[$field] !== $system[$field]) {
                        throw ValidationException::withMessages(['invoice_series_id' => __('billing.messages.system_changed')]);
                    }
                }
                if (now('Europe/Madrid')->lt(Carbon::parse($previous->generated_at))) {
                    throw ValidationException::withMessages(['issue_date' => __('billing.messages.clock')]);
                }
            }
            $hash = VerifactuXml::hash($invoice->issuer_snapshot['tax_identification_number'], $invoice->number, $invoice->issue_date->format('d-m-Y'), $invoice->invoice_type,
                InvoiceCalculator::money(BigDecimal::of($invoice->tax_total)->plus($invoice->surcharge_total)), $invoice->gross_total, $previous?->hash ?? '', $generatedAt);
            $record = BillingRecord::create(['sales_invoice_id' => $invoice->id, 'billing_chain_id' => $chain->id, 'previous_record_id' => $previous?->id, 'hash' => $hash,
                'previous_hash' => $previous?->hash, 'generated_at' => $generatedAt, 'system_snapshot' => $system,
                'xml' => app(VerifactuXml::class)->build($invoice, $previous, $system, $generatedAt, $hash)]);
            $record->submission()->create(['status' => 'pending']);
            DB::table('billing_chains')->where('id', $chain->id)->update(['last_record_id' => $record->id, 'updated_at' => now()]);
            $invoice->status = 'issued';
            $invoice->issued_at = now();
            $invoice->save();

            return $invoice;
        }, 3);
    }

    public function credit(SalesInvoice $original, int $seriesId, string $type, string $reason): SalesInvoice
    {
        Validator::make(['type' => $type, 'reason' => $reason], ['type' => ['required', 'in:R1,R2,R3,R4'], 'reason' => ['required', 'string', 'max:500']])->validate();

        return DB::transaction(function () use ($original, $seriesId, $type, $reason): SalesInvoice {
            $original = SalesInvoice::whereKey($original->id)->lockForUpdate()->firstOrFail();
            if ($original->status !== 'issued' || $original->rectifies_invoice_id) {
                throw ValidationException::withMessages(['status' => __('billing.messages.credit_origin')]);
            }
            $series = InvoiceSeries::findOrFail($seriesId);
            if ($series->kind !== 'rectifying' || $series->environment !== $original->environment) {
                throw ValidationException::withMessages(['invoice_series_id' => __('billing.messages.series')]);
            }
            $credit = new SalesInvoice(['customer_id' => $original->customer_id, 'invoice_series_id' => $seriesId, 'work_order_id' => $original->work_order_id,
                'issue_date' => today('Europe/Madrid')->toDateString(), 'operation_date' => $original->operation_date->toDateString(), 'description' => $reason, 'fiscal_evidence' => $original->fiscal_evidence]);
            $credit->rectifies_invoice_id = $original->id;
            $credit->invoice_type = $type;
            $credit->save();
            foreach ($original->lines as $line) {
                $already = SalesInvoiceLine::where('rectifies_line_id', $line->id)->whereHas('invoice', fn ($q) => $q->whereIn('status', ['draft', 'issued']))->pluck('quantity')->reduce(fn (BigDecimal $sum, string $qty): BigDecimal => $sum->plus($qty), BigDecimal::of('0'));
                $remaining = BigDecimal::of($line->quantity)->plus($already);
                if ($remaining->compareTo('0') <= 0) {
                    continue;
                }
                $new = new SalesInvoiceLine($line->only(['product_id', 'position', 'description', 'unit_label', 'unit_price', 'discount_percentage', 'tax_profile_id', 'withholding_rate_id']));
                $new->sales_invoice_id = $credit->id;
                $new->quantity = (string) $remaining->negated();
                $new->rectifies_line_id = $line->id;
                $new->work_order_line_id = $line->work_order_line_id;
                $new->save();
            }
            if (! $credit->lines()->exists()) {
                throw ValidationException::withMessages(['lines' => __('billing.messages.nothing_pending')]);
            }

            return $credit;
        }, 3);
    }
}
