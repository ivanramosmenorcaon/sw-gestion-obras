<?php

namespace App\Support;

use App\Models\Budget;
use App\Models\ProjectItem;
use App\Models\WorkOrder;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class BudgetEditor
{
    /** @param Collection<int, ProjectItem> $items */
    public function fromProjectItems(Collection $items, string $name): Budget
    {
        validator(['name' => $name], ['name' => ['required', 'string', 'max:255']])->validate();

        return DB::transaction(function () use ($items, $name): Budget {
            $items = ProjectItem::whereKey($items->modelKeys())->orderBy('id')->lockForUpdate()->get();
            if ($items->isEmpty() || $items->pluck('project_id')->unique()->count() !== 1) {
                throw ValidationException::withMessages(['name' => 'Seleccione partidas de una única obra.']);
            }
            abort_unless(OperationalAccess::allows(Budget::class, 'create'), 403);
            $project = $items->first()->project;
            $chapters = $project->projectChapters()->get()->keyBy('id');
            $snapshots = [];
            foreach ($items as $item) {
                abort_unless(OperationalAccess::allows(ProjectItem::class, 'view', $item), 403);
                $path = [];
                $chapter = $chapters->get($item->project_chapter_id);
                $visited = [];
                while ($chapter && ! isset($visited[$chapter->id])) {
                    $visited[$chapter->id] = true;
                    array_unshift($path, $chapter->only(['id', 'code', 'name']));
                    $chapter = $chapters->get($chapter->parent_id);
                }
                $snapshots[] = [
                    'source_line_id' => $item->id, 'source_project_item_id' => $item->id,
                    'source_code' => $item->source_code, 'description' => $item->description, 'unit' => $item->unit_label,
                    'quantity' => $item->planned_quantity, 'price' => $item->estimated_unit_sale_price, 'discount_percentage' => $item->discount_percentage,
                    'chapter_path' => $path, 'measurement_details' => $item->measurement_details ?? [], 'budget_breakdown' => $item->budget_breakdown ?? [],
                ];
            }

            return Budget::create(['project_id' => $project->id, 'reference' => 'PRE-'.Str::ulid(), 'name' => $name, 'currency_code' => $project->currency_code, 'items' => $snapshots]);
        }, 3);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<int, array{included: bool, quantity: string, price: string, unit?: string}>  $selection
     * @return list<array<string, mixed>>
     */
    public function selected(array $items, array $selection, bool $allowEmpty = false): array
    {
        Validator::make(['selection' => $selection], [
            'selection' => [$allowEmpty ? 'present' : 'required', 'array'],
            'selection.*' => ['array:included,quantity,price,unit,discount_percentage'],
            'selection.*.discount_percentage' => ['nullable', 'numeric', 'decimal:0,4', 'between:0,100'],
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
            $item['discount_percentage'] = $choice['discount_percentage'] ?? $item['discount_percentage'] ?? '0';
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
            'lines.*.discount_percentage' => ['nullable', 'numeric', 'decimal:0,4', 'between:0,100'],
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
                    'discount_percentage' => $line['discount_percentage'] ?? '0',
                    'description' => $line['description'], 'unit' => $line['unit'],
                    'quantity' => (string) BigDecimal::of($line['quantity'])->toScale(6),
                    'price' => (string) BigDecimal::of($line['price'])->toScale(6),
                    'chapter_path' => [], 'measurement_details' => [], 'budget_breakdown' => [],
                ];
            }
            $current->update(['items' => $items]);
        });
    }

    public static function fingerprint(Budget $budget): string
    {
        return hash('sha256', serialize([$budget->name, $budget->items, $budget->status, $budget->terms, $budget->exclusions, $budget->valid_until?->toDateString()]));
    }

    public static function decimal(mixed $value): mixed
    {
        return is_string($value) ? str_replace(',', '.', trim($value)) : $value;
    }

    /**
     * @param  array<int, array<string, mixed>>  $selection
     * @param  array<int, array<string, mixed>>  $lines
     * @param  array<string, mixed>  $terms
     */
    public function saveDraft(Budget $budget, string $name, array $selection, array $lines, string $fingerprint, array $terms = []): void
    {
        $terms = array_intersect_key($terms, array_flip(['terms', 'exclusions', 'valid_until']));
        $budget->getConnection()->transaction(function () use ($budget, $name, $selection, $lines, $fingerprint, $terms): void {
            $current = Budget::query()->whereKey($budget->id)->lockForUpdate()->firstOrFail();
            if (! hash_equals(self::fingerprint($current), $fingerprint)) {
                throw ValidationException::withMessages(['name' => 'Este presupuesto ha cambiado en otra sesión. Sus cambios siguen en pantalla; abra otra pestaña para compararlos antes de recargar.']);
            }
            if ($current->status !== 'draft') {
                throw ValidationException::withMessages(['name' => 'Cree una revisión para modificar un presupuesto enviado o aceptado.']);
            }
            Validator::make(['name' => $name, ...$terms], [
                'name' => ['required', 'string', 'max:255'],
                'terms' => ['nullable', 'string', 'max:10000'],
                'exclusions' => ['nullable', 'string', 'max:10000'],
                'valid_until' => ['nullable', 'date', 'after_or_equal:today'],
            ])->validate();
            $source = array_values(array_filter($current->items, fn (array $item): bool => ! ($item['manual'] ?? false)));
            foreach (array_keys($selection) as $id) {
                if (! in_array((int) $id, array_column($current->items, 'source_line_id'), true)) {
                    throw ValidationException::withMessages(['selection' => 'Hay partidas ajenas al documento.']);
                }
            }
            $sourceIds = array_column($source, 'source_line_id');
            $selection = array_intersect_key($selection, array_flip($sourceIds));
            foreach ($selection as &$choice) {
                $choice['quantity'] = self::decimal($choice['quantity'] ?? null);
                $choice['price'] = self::decimal($choice['price'] ?? null);
            }
            unset($choice);
            $items = $this->selected($source, $selection, allowEmpty: true);
            $lines = array_values(array_filter($lines, fn (array $line): bool => filled($line['description'] ?? null) || (string) self::decimal($line['quantity'] ?? '') !== '1' || (string) self::decimal($line['price'] ?? '') !== '0' || (string) self::decimal($line['discount_percentage'] ?? '0') !== '0'));
            foreach ($lines as &$line) {
                $line['quantity'] = self::decimal($line['quantity'] ?? null);
                $line['price'] = self::decimal($line['price'] ?? null);
                $line['discount_percentage'] = self::decimal($line['discount_percentage'] ?? '0');
            }
            unset($line);
            $current->update(['name' => $name, ...$terms, 'items' => $items]);
            $this->saveManualLines($current, $lines);
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
        return (string) BigDecimal::of((string) $item['quantity'])->multipliedBy((string) $item['price'])->multipliedBy(BigDecimal::of(100)->minus((string) ($item['discount_percentage'] ?? '0')))->dividedBy(100, 2, RoundingMode::HalfUp);
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

        $discount = self::decimal($choice['discount_percentage'] ?? '0');
        if (! is_numeric($discount) || ! preg_match('/^\d{1,3}(?:\.\d{0,4})?$/D', (string) $discount) || BigDecimal::of($discount)->compareTo(100) > 0) {
            return null;
        }
        $choice['discount_percentage'] = $discount;

        return self::amount($choice);
    }

    public static function formatAmount(string $amount): string
    {
        [$whole, $fraction] = explode('.', (string) BigDecimal::of($amount)->toScale(2, RoundingMode::HalfUp));

        return preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole).','.$fraction;
    }
}
