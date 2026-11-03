<?php

namespace App\Support;

use App\Models\CompanySetting;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLine;
use App\Models\TaxRate;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

final class InvoiceCalculator
{
    public static function money(string|BigDecimal $value): string
    {
        return (string) BigDecimal::of($value)->toScale(2, RoundingMode::HalfUp);
    }

    public static function percentage(string $base, string $percentage): string
    {
        return (string) BigDecimal::of($base)->multipliedBy($percentage)->dividedBy('100', 2, RoundingMode::HalfUp);
    }

    /** @return array{lines: array<int, array<string, mixed>>, groups: array<int, array<string, mixed>>, totals: array<string, string>} */
    public function calculate(SalesInvoice $invoice, bool $issuing = false): array
    {
        $invoice->loadMissing('lines.taxProfile.taxRate', 'lines.taxProfile.surchargeRate', 'lines.withholdingRate', 'customer');
        $lines = [];
        $groups = [];
        $members = [];
        foreach ($invoice->lines as $line) {
            $profile = $line->taxProfile;
            $fiscal = $line->rectifies_line_id ? $line->rectifiesLine->fiscal_snapshot : [
                'tax_system' => $profile->tax_system, 'regime_code' => $profile->regime_code,
                'operation_class' => $profile->operation_class, 'exemption_code' => $profile->exemption_code,
                'tax_percentage' => $profile->taxRate?->percentage,
                'surcharge_percentage' => $profile->surchargeRate?->percentage,
                'withholding_percentage' => $line->withholdingRate?->percentage,
                'legal_basis' => $profile->legal_basis,
            ];
            if ($issuing) {
                $this->validateFiscal($invoice, $line);
            }
            $base = self::money(BigDecimal::of($line->quantity)->multipliedBy($line->unit_price)->multipliedBy(BigDecimal::of('100')->minus($line->discount_percentage))->dividedBy('100', 12, RoundingMode::HalfUp));
            $tax = self::percentage($base, $fiscal['tax_percentage'] ?? '0');
            $surcharge = self::percentage($base, $fiscal['surcharge_percentage'] ?? '0');
            $withholding = self::percentage($base, $fiscal['withholding_percentage'] ?? '0');
            if ($line->rectifies_line_id) {
                $original = $line->rectifiesLine;
                $otherCredits = SalesInvoiceLine::where('rectifies_line_id', $original->id)->whereKeyNot($line->id)->whereHas('invoice', fn ($query) => $query->where('status', 'issued'))->get();
                $remainingQuantity = $otherCredits->reduce(fn (BigDecimal $sum, SalesInvoiceLine $credit): BigDecimal => $sum->plus($credit->quantity), BigDecimal::of($original->quantity));
                foreach (['net_amount' => 'base', 'tax_amount' => 'tax', 'surcharge_amount' => 'surcharge', 'withholding_amount' => 'withholding'] as $field => $variable) {
                    $available = $otherCredits->reduce(fn (BigDecimal $sum, SalesInvoiceLine $credit): BigDecimal => $sum->plus($credit->$field), BigDecimal::of($original->$field));
                    if (BigDecimal::of($line->quantity)->abs()->compareTo($remainingQuantity) === 0) {
                        $$variable = self::money($available->negated());
                    } elseif (BigDecimal::of($$variable)->abs()->compareTo($available->abs()) > 0) {
                        $$variable = self::money($available->negated());
                    }
                }
            }
            $lines[$line->id] = ['fiscal_snapshot' => $fiscal, 'net_amount' => $base, 'tax_amount' => $tax, 'surcharge_amount' => $surcharge, 'withholding_amount' => $withholding];
            $key = json_encode(array_diff_key($fiscal, array_flip(['withholding_percentage', 'legal_basis'])), JSON_THROW_ON_ERROR);
            $groups[$key] ??= $fiscal + ['base' => '0.00', 'tax' => '0.00', 'surcharge' => '0.00'];
            if ($invoice->rectifies_invoice_id) {
                $groups[$key]['tax'] = self::money(BigDecimal::of($groups[$key]['tax'])->plus($tax));
                $groups[$key]['surcharge'] = self::money(BigDecimal::of($groups[$key]['surcharge'])->plus($surcharge));
            }
            $groups[$key]['base'] = self::money(BigDecimal::of($groups[$key]['base'])->plus($base));
            $members[$key][] = $line->id;
        }
        foreach ($groups as $key => &$group) {
            if ($invoice->rectifies_invoice_id) {
                continue;
            }
            $group['tax'] = self::percentage($group['base'], $group['tax_percentage'] ?? '0');
            $group['surcharge'] = self::percentage($group['base'], $group['surcharge_percentage'] ?? '0');
            foreach (['tax' => 'tax_amount', 'surcharge' => 'surcharge_amount'] as $groupField => $lineField) {
                $sum = BigDecimal::of('0');
                foreach ($members[$key] as $id) {
                    $sum = $sum->plus($lines[$id][$lineField]);
                }
                $difference = BigDecimal::of($group[$groupField])->minus($sum);
                foreach ($members[$key] as $id) {
                    if ($difference->compareTo('0') === 0) {
                        break;
                    }
                    $adjustment = $difference->compareTo('0') > 0 ? '0.01' : '-0.01';
                    $lines[$id][$lineField] = self::money(BigDecimal::of($lines[$id][$lineField])->plus($adjustment));
                    $difference = $difference->minus($adjustment);
                }
            }
        }
        unset($group);
        if (count($groups) > 12) {
            throw ValidationException::withMessages(['lines' => __('billing.messages.max_groups')]);
        }
        $totals = ['net_total' => '0.00', 'tax_total' => '0.00', 'surcharge_total' => '0.00', 'withholding_total' => '0.00'];
        foreach ($lines as $line) {
            foreach (['net_total' => 'net_amount', 'tax_total' => 'tax_amount', 'surcharge_total' => 'surcharge_amount', 'withholding_total' => 'withholding_amount'] as $target => $source) {
                $totals[$target] = self::money(BigDecimal::of($totals[$target])->plus($line[$source]));
            }
        }
        $totals['gross_total'] = self::money(BigDecimal::of($totals['net_total'])->plus($totals['tax_total'])->plus($totals['surcharge_total']));
        $totals['payable_total'] = self::money(BigDecimal::of($totals['gross_total'])->minus($totals['withholding_total']));

        return ['lines' => $lines, 'groups' => array_values($groups), 'totals' => $totals];
    }

    private function validateFiscal(SalesInvoice $invoice, SalesInvoiceLine $line): void
    {
        if ($line->rectifies_line_id) {
            return;
        }
        $profile = $line->taxProfile;
        FiscalValidator::validate($profile);
        if (! $profile->is_active || $profile->tax_system !== '01' || ! in_array($profile->regime_code, ['01', '18'], true)) {
            throw ValidationException::withMessages(['tax_profile_id' => __('billing.messages.unsupported_regime')]);
        }
        foreach ([$profile->taxRate, $profile->surchargeRate, $line->withholdingRate] as $rate) {
            if ($rate instanceof TaxRate && (! $rate->is_active || $rate->valid_from->gt($invoice->operation_date) || ($rate->valid_until && $rate->valid_until->lt($invoice->operation_date)))) {
                throw ValidationException::withMessages(['tax_profile_id' => __('billing.messages.rate_date')]);
            }
        }
        $evidenceRequired = $profile->requires_evidence || collect([$profile->taxRate, $profile->surchargeRate, $line->withholdingRate])->filter()->contains(fn (TaxRate $rate): bool => $rate->requires_evidence);
        if ($evidenceRequired && blank($invoice->fiscal_evidence)) {
            throw ValidationException::withMessages(['fiscal_evidence' => __('billing.messages.evidence')]);
        }
        $issuerNif = CompanySetting::where('scope', 'company')->value('tax_identification_number');
        if ($line->withholding_rate_id && ! preg_match('/^(?:[0-9]{8}[A-Z]|[XYZKLM][0-9]{7}[A-Z])$/', $issuerNif ?? '')) {
            throw ValidationException::withMessages(['withholding_rate_id' => __('billing.messages.withholding_issuer')]);
        }
        if ($profile->operation_class === 'S2' && ! in_array($invoice->customer->taxpayer_capacity, ['business', 'public_body'], true)) {
            throw ValidationException::withMessages(['tax_profile_id' => __('billing.messages.reverse_customer')]);
        }
        if ($line->withholding_rate_id && ($line->withholdingRate->kind !== 'withholding' || ! $invoice->customer->withholding_obliged || ! in_array($invoice->customer->taxpayer_capacity, ['business', 'public_body'], true))) {
            throw ValidationException::withMessages(['withholding_rate_id' => __('billing.messages.withholding')]);
        }
        if ($profile->surcharge_rate_id && (! $invoice->customer->equivalence_surcharge_applicable || $line->product?->type !== 'material')) {
            throw ValidationException::withMessages(['tax_profile_id' => __('billing.messages.surcharge')]);
        }
        if (in_array($profile->operation_class, ['exempt', 'N1', 'N2', 'S2'], true) && blank($profile->legal_basis)) {
            throw ValidationException::withMessages(['tax_profile_id' => __('billing.messages.legal_basis')]);
        }
    }
}
