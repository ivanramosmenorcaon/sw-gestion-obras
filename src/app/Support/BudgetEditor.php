<?php

namespace App\Support;

use App\Models\Budget;
use App\Models\WorkOrder;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class BudgetEditor
{
    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<int, array{included: bool, quantity: string, price: string, unit?: string}>  $selection
     * @return list<array<string, mixed>>
     */
    public function selected(array $items, array $selection, bool $allowEmpty = false): array
    {
        Validator::make(['selection' => $selection], [
            'selection' => [$allowEmpty ? 'present' : 'required', 'array'],
            'selection.*' => ['array:included,quantity,price,unit'],
            'selection.*.unit' => ['sometimes', 'required', 'string', 'max:100'],
            'selection.*.included' => ['required', 'boolean'],
            'selection.*.quantity' => ['required_if:selection.*.included,true', 'nullable', 'numeric', 'decimal:0,6', 'min:0', 'max:99999999999999.999999'],
            'selection.*.price' => ['required_if:selection.*.included,true', 'nullable', 'numeric', 'decimal:0,6', 'min:0', 'max:99999999999999.999999'],
        ])->validate();
        $itemIds = array_column($items, 'source_line_id');
        foreach (array_keys($selection) as $index) {
            if (! in_array((int) $index, $itemIds, true)) {
                throw ValidationException::withMessages(['selection' => 'Hay partidas que no pertenecen a este documento. Recargue la página.']);
            }
        }
        $selected = [];
        foreach ($items as $item) {
            $choice = $selection[$item['source_line_id']] ?? null;
            if ($choice === null || ! $choice['included']) {
                continue;
            }
            $item['quantity'] = (string) BigDecimal::of($choice['quantity'])->toScale(6);
            $item['unit'] = $choice['unit'] ?? $item['unit'];
            $item['price'] = (string) BigDecimal::of($choice['price'])->toScale(6);
            $selected[] = $item;
        }
        if ($selected === [] && ! $allowEmpty) {
            throw ValidationException::withMessages(['selection' => 'Incluya al menos una partida en el presupuesto.']);
        }

        return $selected;
    }

    /** @param array<int, array{included: bool, quantity: string, price: string, unit?: string}> $selection */
    public function create(WorkOrder $order, string $name, array $selection): Budget
    {
        Validator::make(['name' => $name], ['name' => ['required', 'string', 'max:255']])->validate();

        return $order->getConnection()->transaction(function () use ($order, $name, $selection): Budget {
            $order = WorkOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $items = app(WorkOrderChapterTree::class)->snapshots($order);

            return Budget::query()->create([
                'project_id' => $order->project_id, 'source_work_order_id' => $order->id,
                'reference' => 'PRE-'.Str::ulid(), 'name' => $name, 'currency_code' => $order->project->currency_code,
                'items' => $this->selected($items, $selection),
            ]);
        });
    }

    /** @param array<int, array{included: bool, quantity: string, price: string, unit?: string}> $selection */
    public function update(Budget $budget, string $name, array $selection): void
    {
        Validator::make(['name' => $name], ['name' => ['required', 'string', 'max:255']])->validate();
        $budget->getConnection()->transaction(function () use ($budget, $name, $selection): void {
            $current = Budget::query()->whereKey($budget->id)->lockForUpdate()->firstOrFail();
            $current->update(['name' => $name, 'items' => $current->items === [] && $selection === [] ? [] : $this->selected($current->items, $selection, allowEmpty: true)]);
        });
    }

    /** @param list<array<string, mixed>> $lines */
    public function saveManualLines(Budget $budget, array $lines): void
    {
        Validator::make(['lines' => $lines], [
            'lines' => ['present', 'array'],
            'lines.*.description' => ['required', 'string', 'max:5000'],
            'lines.*.unit' => ['required', 'string', 'max:100'],
            'lines.*.quantity' => ['required', 'numeric', 'decimal:0,6', 'min:0', 'max:99999999999999.999999'],
            'lines.*.price' => ['required', 'numeric', 'decimal:0,6', 'min:0', 'max:99999999999999.999999'],
        ])->validate();
        $budget->getConnection()->transaction(function () use ($budget, $lines): void {
            $current = Budget::query()->whereKey($budget->id)->lockForUpdate()->firstOrFail();
            $items = array_values(array_filter($current->items, fn (array $item): bool => ! ($item['manual'] ?? false)));
            foreach ($lines as $index => $line) {
                $items[] = [
                    'source_line_id' => -($index + 1), 'source_code' => null, 'manual' => true,
                    'description' => $line['description'], 'unit' => $line['unit'],
                    'quantity' => (string) BigDecimal::of($line['quantity'])->toScale(6),
                    'price' => (string) BigDecimal::of($line['price'])->toScale(6),
                    'chapter_path' => [], 'measurement_details' => [], 'budget_breakdown' => [],
                ];
            }
            $current->update(['items' => $items]);
        });
    }

    /** @param list<array<string, mixed>> $items */
    public static function total(array $items): string
    {
        $total = BigDecimal::of(0);
        foreach ($items as $item) {
            $total = $total->plus(self::amount($item));
        }

        return (string) $total->toScale(2);
    }

    /** @param array<string, mixed> $item */
    public static function amount(array $item): string
    {
        return (string) BigDecimal::of((string) $item['quantity'])->multipliedBy((string) $item['price'])->toScale(2, RoundingMode::HalfUp);
    }

    /** @param array<string, mixed> $choice */
    public static function previewAmount(array $choice): ?string
    {
        if (! is_numeric($choice['quantity'] ?? null) || ! is_numeric($choice['price'] ?? null)
            || strlen((string) $choice['quantity']) > 32 || strlen((string) $choice['price']) > 32) {
            return null;
        }

        foreach (['quantity', 'price'] as $field) {
            if (! preg_match('/^(?:\d{1,14}(?:\.\d{0,6})?|\.\d{1,6})$/D', (string) $choice[$field])) {
                return null;
            }
        }

        return self::amount($choice);
    }

    public static function formatAmount(string $amount): string
    {
        [$whole, $fraction] = explode('.', (string) BigDecimal::of($amount)->toScale(2, RoundingMode::HalfUp));

        return preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole).','.$fraction;
    }
}
