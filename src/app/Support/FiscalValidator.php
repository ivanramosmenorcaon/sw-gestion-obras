<?php

namespace App\Support;

use App\Models\TaxRate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidatorInstance;

final class FiscalValidator
{
    public static function validate(Model $record): void
    {
        $table = $record->getTable();
        $data = $record->getAttributes();
        $rules = [];

        foreach (['country_code', 'tax_country_code'] as $field) {
            if (array_key_exists($field, $data)) {
                $rules[$field] = ['required', Rule::in(array_keys(FiscalCatalog::countries()))];
            }
        }

        if (in_array($table, ['parties', 'products', 'company_settings'], true)) {
            foreach (['sales_tax_profile_id', 'purchase_tax_profile_id'] as $field) {
                $rules[$field] = ['nullable', 'integer', 'exists:tax_profiles,id'];
            }
            foreach (['sales_withholding_rate_id', 'purchase_withholding_rate_id'] as $field) {
                $rules[$field] = ['nullable', 'integer', Rule::exists('tax_rates', 'id')->where('kind', 'withholding')];
            }
            $rules['fiscal_notes'] = ['nullable', 'string'];
            if ($table !== 'products') {
                $rules['taxpayer_capacity'] = ['nullable', Rule::in(array_keys(__('fiscal.capacities')))];
                $rules['tax_territory'] = ['nullable', Rule::in(array_keys(__('fiscal.territories')))];
                $rules['iae_code'] = ['nullable', 'string', 'max:30'];
                $rules['withholding_obliged'] = ['nullable', 'boolean'];
                $rules['equivalence_surcharge_applicable'] = ['nullable', 'boolean'];
                $rules['legal_name'] = ['required', 'string', 'max:120'];
                $rules['tax_identification_type'] = ['required', Rule::in($table === 'company_settings' ? ['nif'] : array_keys(FiscalCatalog::identityTypes()))];
                $rules['tax_identification_number'] = [$table === 'company_settings' ? 'required' : 'nullable', 'string', ($data['tax_identification_type'] ?? 'nif') === 'nif' ? 'max:9' : 'max:20'];
            }
        }

        if (in_array($table, ['tax_rates', 'tax_profiles'], true)) {
            $rules = array_merge($rules, [
                'code' => ['required', 'string', 'max:60', Rule::unique($table, 'code')->ignore($record->getKey())],
                'name' => ['required', 'string', 'max:255'],
                'legal_basis' => ['nullable', 'string'],
                'requires_evidence' => ['required', 'boolean'],
                'is_active' => ['required', 'boolean'],
            ]);
        }

        if ($table === 'tax_rates') {
            $rules = array_merge($rules, [
                'kind' => ['required', Rule::in(array_keys(__('fiscal.rate_kinds')))],
                'percentage' => ['required', 'numeric', 'decimal:0,4', 'between:0,100'],
                'valid_from' => ['required', 'date'],
                'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            ]);
        }

        if ($table === 'tax_profiles') {
            $rules = array_merge($rules, [
                'tax_system' => ['required', Rule::in(['01', '02', '03', '05'])],
                'regime_code' => [($data['tax_system'] ?? '01') === '05' ? 'nullable' : 'required', Rule::in(array_keys(FiscalCatalog::regimes($data['tax_system'] ?? null)))],
                'operation_class' => ['required', Rule::in(['S1', 'S2', 'N1', 'N2', 'exempt'])],
                'exemption_code' => ['nullable', 'required_if:operation_class,exempt', Rule::in(array_keys(FiscalCatalog::exemptions($data['tax_system'] ?? null)))],
                'tax_rate_id' => ['nullable', 'required_if:operation_class,S1,S2', 'integer', 'exists:tax_rates,id'],
                'surcharge_rate_id' => ['nullable', 'integer', Rule::exists('tax_rates', 'id')->where('kind', 'surcharge')],
                'legal_basis' => ['nullable', 'string', 'required_if:operation_class,exempt,S2,N1,N2'],
            ]);
        }

        if ($rules === []) {
            return;
        }

        $validator = Validator::make($data, $rules, [], array_merge(__('business.fields'), __('fiscal.fields')));
        $validator->after(function (ValidatorInstance $validator) use ($record, $data, $table): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            if ($table === 'tax_rates' && $record->kind === 'vat' && ! in_array($record->percentage, ['0.0000', '2.0000', '4.0000', '5.0000', '7.5000', '10.0000', '21.0000'], true)) {
                $validator->errors()->add('percentage', __('validation.in', ['attribute' => __('fiscal.fields.percentage')]));
            }
            if ($table === 'tax_rates' && $record->kind !== 'withholding' && (! preg_match('/^[0-9]{1,2}\.[0-9]{2}00$/', $record->percentage))) {
                $validator->errors()->add('percentage', __('validation.decimal', ['attribute' => __('fiscal.fields.percentage'), 'min' => 0, 'max' => 2]));
            }
            if ($table === 'parties' && ! empty($data['tax_identification_number'])) {
                $type = $record->tax_identification_type;
                $country = $record->tax_country_code;
                if (($type === 'nif' || $type === '07') && $country !== 'ES') {
                    $validator->errors()->add('tax_country_code', __('fiscal.messages.identity_country'));
                }
                if ($country === 'ES' && ! in_array($type, ['nif', '03', '07'], true)) {
                    $validator->errors()->add('tax_identification_type', __('fiscal.messages.identity_country'));
                }
            }
            if ($table !== 'tax_profiles') {
                return;
            }
            $operation = $record->operation_class;
            $rate = $record->tax_rate_id ? TaxRate::query()->find($record->tax_rate_id) : null;
            $surcharge = $record->surcharge_rate_id ? TaxRate::query()->find($record->surcharge_rate_id) : null;
            $kind = ['01' => 'vat', '02' => 'ipsi', '03' => 'igic', '05' => 'other'][$record->tax_system];
            if ($rate && $rate->kind !== $kind) {
                $validator->errors()->add('tax_rate_id', __('fiscal.messages.wrong_kind'));
            }
            if (in_array($operation, ['exempt', 'N1', 'N2'], true) && ($rate || $surcharge)) {
                $validator->errors()->add('tax_rate_id', __('fiscal.messages.no_rate'));
            }
            if ($operation !== 'exempt' && $record->exemption_code !== null) {
                $validator->errors()->add('exemption_code', __('fiscal.messages.invalid_profile'));
            }
            if ($operation === 'S2' && ($surcharge || ! preg_match('/^0(?:\.0+)?$/', (string) $rate?->percentage))) {
                $validator->errors()->add('tax_rate_id', __('fiscal.messages.reverse_zero'));
            }
            if ($surcharge) {
                $pairs = ['21.0000' => ['5.2000', '1.7500'], '10.0000' => ['1.4000'], '4.0000' => ['0.5000'], '0.0000' => ['0.0000'], '2.0000' => ['0.2600'], '5.0000' => ['0.6200'], '7.5000' => ['1.0000']];
                if ($record->tax_system !== '01' || $operation !== 'S1' || ! in_array($surcharge->percentage, $pairs[$rate?->percentage] ?? [], true)) {
                    $validator->errors()->add('surcharge_rate_id', __('fiscal.messages.surcharge_pair'));
                }
            }
            $regime = $record->regime_code;
            $shared = in_array($record->tax_system, ['01', '03'], true);
            $invalid = $shared && (($regime === '02' && $operation !== 'exempt')
                || ($regime === '08' && $operation !== 'N2')
                || ($regime === '10' && $operation !== 'N1')
                || ($regime === '03' && ! in_array($operation, ['S1', 'exempt'], true))
                || ($regime === '04' && ! in_array($operation, ['S2', 'exempt'], true))
                || ($regime === '07' && (in_array($operation, ['S2', 'N1', 'N2'], true) || in_array($record->exemption_code, ['E2', 'E3', 'E4', 'E5'], true)))
                || ($regime === '01' && in_array($record->exemption_code, ['E2', 'E3'], true)));
            $invalid = $invalid
                || ($record->tax_system === '01' && $regime === '11' && $rate?->percentage !== '21.0000')
                || ($record->tax_system === '03' && $regime === '20' && $operation !== 'N2');
            if ($invalid) {
                $validator->errors()->add('regime_code', __('fiscal.messages.invalid_profile'));
            }
        });
        $validator->validate();
    }
}
