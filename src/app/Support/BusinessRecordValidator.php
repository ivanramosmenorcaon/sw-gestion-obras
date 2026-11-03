<?php

namespace App\Support;

use App\Models\Party;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProjectChapter;
use App\Models\Unit;
use App\Models\WorkOrder;
use App\Models\WorkOrderPhase;
use Carbon\Carbon;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidatorInstance;

final class BusinessRecordValidator
{
    public static function validate(Model $record): void
    {
        foreach (['country_code', 'tax_country_code', 'currency_code'] as $field) {
            if ($record->getAttribute($field) !== null) {
                $record->setAttribute($field, strtoupper(trim($record->getAttribute($field))));
            }
        }

        if ($record->getAttribute('tax_identification_number') !== null) {
            $record->setAttribute('tax_identification_number', preg_replace('/\s+/', '', strtoupper($record->getAttribute('tax_identification_number'))));
        }

        if ($record->getTable() === 'work_order_lines' && ! $record->project_id && $record->work_order_id) {
            $record->project_id = WorkOrder::query()->find($record->work_order_id)?->project_id;
        }

        $data = $record->getAttributes();
        $rules = BusinessRecordRules::FIELDS[$record->getTable()];

        foreach (BusinessRecordRules::UNIQUE[$record->getTable()] as $columns) {
            if (collect($columns)->contains(fn (string $column): bool => ($data[$column] ?? null) === null)) {
                continue;
            }

            $column = array_pop($columns);
            $unique = Rule::unique($record->getTable(), $column)->ignore($record->getKey(), $record->getKeyName());
            foreach ($columns as $scope) {
                $unique->where($scope, $data[$scope]);
            }
            $rules[$column][] = $unique;
        }

        foreach (['planned_end_date' => 'planned_start_date', 'actual_end_date' => 'actual_start_date', 'employment_end_date' => 'employment_start_date', 'valid_until' => 'valid_from'] as $end => $start) {
            if (! empty($data[$start]) && isset($rules[$end])) {
                $rules[$end][] = 'after_or_equal:'.$start;
            }
        }

        $validator = Validator::make($data, $rules, [], __('business.fields'));
        $validator->after(function (ValidatorInstance $validator) use ($record, $data): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $table = $record->getTable();
            $fail = fn (string $field, string $message): MessageBag => $validator->errors()->add($field, __('business.messages.'.$message));

            foreach (['customer_id' => 'customer', 'supplier_id' => 'supplier', 'contractor_id' => 'subcontractor'] as $field => $role) {
                if (! empty($data[$field]) && (! $record->exists || $record->isDirty($field))) {
                    if (! Party::query()->find($data[$field])->partyRoles()->where('role', $role)->exists()) {
                        $validator->errors()->add($field, __('business.messages.role_required', ['role' => __('business.options.'.$role)]));
                    }
                }
            }

            if (isset($data['work_order_id'], $data['project_id']) && WorkOrder::query()->find($data['work_order_id'])->project_id !== (int) $data['project_id']) {
                $fail('work_order_id', 'wrong_scope');
            }

            if ($table === 'budgets' && ! empty($data['source_work_order_id']) && WorkOrder::query()->find($data['source_work_order_id'])->project_id !== (int) $data['project_id']) {
                $fail('source_work_order_id', 'wrong_scope');
            }

            foreach (['project_chapter_id' => ['project_id', ProjectChapter::class], 'work_order_phase_id' => ['work_order_id', WorkOrderPhase::class]] as $field => [$scope, $model]) {
                if (! empty($data[$field]) && ! $model::query()->whereKey($data[$field])->where($scope, $data[$scope] ?? null)->exists()) {
                    $fail($field, 'wrong_scope');
                }
            }

            if (! empty($data['product_unit_id'])) {
                if (empty($data['product_id']) || ! ProductUnit::query()->whereKey($data['product_unit_id'])->where('product_id', $data['product_id'])->exists()) {
                    $fail('product_unit_id', 'wrong_scope');
                }
            }

            if ($table === 'work_order_lines' && (! $record->exists || $record->isDirty(['product_id', 'product_unit_id', 'unit_id', 'base_unit_id', 'type']))) {
                $baseUnit = Unit::query()->find($record->base_unit_id);
                if ($baseUnit->magnitude !== Unit::query()->find($record->unit_id)->magnitude) {
                    $fail('unit_id', 'incompatible_unit');
                }
                if ($record->product_id) {
                    $product = Product::query()->find($record->product_id);
                    if ((int) $product->base_unit_id !== (int) $record->base_unit_id || $product->type !== $record->type) {
                        $fail('product_id', 'wrong_scope');
                    }
                }
            }

            if (in_array($table, ['product_categories', 'project_chapters'], true) && ! empty($data['parent_id'])) {
                $parentId = $data['parent_id'];
                $visited = [];
                while ($parentId) {
                    if ((string) $parentId === (string) $record->getKey() || isset($visited[$parentId])) {
                        $fail('parent_id', 'cycle');
                        break;
                    }
                    $visited[$parentId] = true;
                    $parent = $record->newQuery()->find($parentId);
                    if ($table === 'project_chapters' && $parent->project_id !== (int) $record->project_id) {
                        $fail('parent_id', 'wrong_scope');
                        break;
                    }
                    $parentId = $parent->parent_id;
                }
            }

            if ($table === 'unit_conversions') {
                if ((int) $record->from_unit_id === (int) $record->to_unit_id || Unit::query()->find($record->from_unit_id)->magnitude !== Unit::query()->find($record->to_unit_id)->magnitude) {
                    $fail('to_unit_id', 'same_magnitude');
                }
            }

            if ($table === 'product_units') {
                $product = Product::query()->find($record->product_id);
                if ($product->baseUnit->magnitude !== Unit::query()->find($record->unit_id)->magnitude) {
                    $fail('unit_id', 'incompatible_unit');
                }
                if ($record->code === 'base' && ((int) $record->unit_id !== (int) $product->base_unit_id || ! preg_match('/^1(?:\.0+)?$/', (string) $record->base_unit_factor))) {
                    $fail('base_unit_factor', 'base_presentation');
                }
            }

            if ($table === 'products' && $record->exists && $record->isDirty('base_unit_id') && $record->productUnits()->exists()) {
                $fail('base_unit_id', 'base_unit_locked');
            }

            if ($table === 'payment_term_installments') {
                $total = $record->newQuery()->where('payment_term_id', $record->payment_term_id)->when($record->exists, fn (Builder $q): Builder => $q->whereKeyNot($record->getKey()))->pluck('percentage')->sum(fn (mixed $value): int => self::percentageUnits((string) $value));
                if ($total + self::percentageUnits((string) $record->percentage) > 1000000) {
                    $fail('percentage', 'percentage_total');
                }
            }

            if ($table === 'price_list_items') {
                $from = Carbon::parse($record->valid_from)->toDateString();
                $until = $record->valid_until ? Carbon::parse($record->valid_until)->toDateString() : null;
                $overlap = $record->newQuery()->where('price_list_id', $record->price_list_id)->where('product_unit_id', $record->product_unit_id)->where('minimum_quantity', $record->minimum_quantity)
                    ->when($record->exists, fn (Builder $q): Builder => $q->whereKeyNot($record->getKey()))
                    ->when($until, fn (Builder $q): Builder => $q->whereDate('valid_from', '<=', $until))
                    ->where(fn (Builder $q): Builder => $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', $from))->exists();
                if ($overlap) {
                    $fail('valid_from', 'overlap');
                }
            }

            foreach (['party_addresses' => 'is_fiscal', 'party_contacts' => 'is_primary'] as $target => $field) {
                if ($table === $target && $record->getAttribute($field) && $record->newQuery()->where('party_id', $record->party_id)->where($field, true)->when($record->exists, fn (Builder $q): Builder => $q->whereKeyNot($record->getKey()))->exists()) {
                    $validator->errors()->add($field, __('validation.unique', ['attribute' => __('business.fields.'.$field)]));
                }
            }
        });
        $validator->validate();
        FiscalValidator::validate($record);
    }

    public static function canDelete(Model $record): bool
    {
        if ($record instanceof WorkOrder && $record->status === 'draft') {
            return true;
        }

        if ($record->getTable() === 'company_settings' || ($record->getTable() === 'product_units' && $record->code === 'base')) {
            return false;
        }

        foreach (BusinessRecordRules::CHILDREN[$record->getTable()] ?? [] as $relation) {
            $attribute = Str::snake($relation).'_exists';
            $exists = array_key_exists($attribute, $record->getAttributes())
                ? $record->getAttribute($attribute)
                : $record->{$relation}()->exists();
            if ($exists) {
                return false;
            }
        }

        return true;
    }

    public static function inForm(Closure $operation, string $statePath): mixed
    {
        try {
            return $operation();
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $field => $messages) {
                $errors[filled($statePath) ? $statePath.'.'.$field : $field] = $messages;
            }
            throw ValidationException::withMessages($errors);
        }
    }

    private static function percentageUnits(string $percentage): int
    {
        [$whole, $fraction] = array_pad(explode('.', $percentage, 2), 2, '');

        return ((int) $whole * 10000) + (int) str_pad(substr($fraction, 0, 4), 4, '0');
    }
}
