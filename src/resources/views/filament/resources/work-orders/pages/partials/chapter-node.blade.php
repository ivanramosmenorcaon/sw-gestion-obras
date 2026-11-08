@php
    $allIncluded = collect($chapter['item_keys'])->every(fn ($index) => $this->selection[$items[$index]['source_line_id']]['included'] ?? false);
    $subtotal = \Brick\Math\BigDecimal::of(0);
    foreach ($chapter['item_keys'] as $index) {
        $choice = $this->selection[$items[$index]['source_line_id']] ?? [];
        if (($choice['included'] ?? false) && ($amount = \App\Support\BudgetEditor::previewAmount($choice)) !== null) { $subtotal = $subtotal->plus($amount); }
    }
@endphp
<details open x-show="!search || $el.textContent.toLocaleLowerCase().includes(search.toLocaleLowerCase())" data-chapter="{{ $chapter['key'] }}" class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
    <summary class="cursor-pointer bg-gray-50 p-4 marker:text-amber-500 dark:bg-gray-800">
        <span class="inline-flex max-w-full flex-wrap items-center gap-3 align-middle">
            <x-filament::badge color="primary" icon="heroicon-o-folder">Capítulo {{ $chapter['code'] }}</x-filament::badge>
            <span class="font-semibold text-gray-950 dark:text-white">{{ $chapter['name'] }}</span>
            <span class="text-xs text-gray-500 dark:text-gray-400">{{ count($chapter['item_keys']) }} partidas</span>
            <span class="text-sm font-semibold tabular-nums text-gray-950 dark:text-white">{{ \App\Support\BudgetEditor::formatAmount((string) $subtotal) }} {{ $currency }}</span>
        </span>
    </summary>
    <div class="space-y-4 p-4">
        @if ($canBudget)
            <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                <input type="checkbox" @checked($allIncluded) wire:change="toggleChapter('{{ $chapter['key'] }}', $event.target.checked)" class="rounded border-gray-300 text-amber-600 focus:ring-amber-500 dark:border-gray-600 dark:bg-gray-800">
                Incluir todo este capítulo y sus subcapítulos
            </label>
        @endif
        @foreach ($chapter['items'] as $index)
            @php
                $item = $items[$index];
                $id = $item['source_line_id'];
                $choice = $this->selection[$id] ?? ['included' => false, 'quantity' => $item['quantity'], 'price' => $item['price']];
                $amount = \App\Support\BudgetEditor::previewAmount($choice);
            @endphp
            <details data-budget-item="{{ $id }}" x-show="!search || $el.textContent.toLocaleLowerCase().includes(search.toLocaleLowerCase())" wire:key="budget-item-{{ $id }}" @class(['rounded-lg border p-4', 'border-gray-200 dark:border-gray-700' => $choice['included'], 'border-dashed border-gray-200 bg-gray-50 opacity-60 dark:border-gray-700 dark:bg-gray-800' => !$choice['included']])>
                <summary class="cursor-pointer marker:text-amber-500">
                    <span class="inline-flex max-w-full flex-wrap items-center gap-2 align-middle">
                        <x-filament::badge color="gray">Partida {{ $item['source_code'] ?? $id }}</x-filament::badge>
                        <span class="font-semibold text-gray-950 dark:text-white">{{ strtok($item['description'], "\n") ?: $item['description'] }}</span>
                        <span class="text-sm font-semibold tabular-nums text-gray-950 dark:text-white">{{ $choice['included'] && $amount !== null ? \App\Support\BudgetEditor::formatAmount($amount) . ' ' . $currency : '—' }}</span>
                        @if (!$choice['included']) <span class="text-xs text-gray-500">Excluida del presupuesto</span> @endif
                    </span>
                </summary>
                <div class="mt-4 flex items-start gap-3">
                    @if ($canBudget)
                        <input type="checkbox" wire:model.live="selection.{{ $id }}.included" aria-label="Incluir partida {{ $item['source_code'] }}" class="mt-1 rounded border-gray-300 text-amber-600 focus:ring-amber-500 dark:border-gray-600 dark:bg-gray-800">
                    @endif
                    <div class="min-w-0 flex-1">
                        @if ($editingBudget && $canBudget && $choice['included'])
                            <x-filament::button type="button" color="danger" size="sm" wire:click="removeBudgetLine({{ $id }})">
                                Eliminar línea
                            </x-filament::button>
                        @endif
                        <details class="mt-2">
                            <summary class="cursor-pointer text-xs text-gray-500 dark:text-gray-400">Ver descripción completa</summary>
                            <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-gray-700 dark:text-gray-200">{{ $item['description'] }}</p>
                        </details>
                        <div class="mt-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
                            <label class="text-xs font-medium text-gray-600 dark:text-gray-300">Cantidad
                                <x-filament::input.wrapper class="mt-1"><x-filament::input type="number" min="0" step="0.000001" wire:model.live.debounce.300ms="selection.{{ $id }}.quantity" :disabled="!$canBudget || !$choice['included']" /></x-filament::input.wrapper>
                                @error('selection.'.$id.'.quantity') <span class="text-red-600">{{ $message }}</span> @enderror
                            </label>
                            <label class="text-xs font-medium text-gray-600 dark:text-gray-300">Unidad
                                <x-filament::input.wrapper class="mt-1"><x-filament::input wire:model.blur="selection.{{ $id }}.unit" :disabled="!$canBudget || !$choice['included']" /></x-filament::input.wrapper>
                                @error('selection.'.$id.'.unit') <span class="text-red-600">{{ $message }}</span> @enderror
                            </label>
                            <label class="text-xs font-medium text-gray-600 dark:text-gray-300">Precio unitario ({{ $currency }})
                                <x-filament::input.wrapper class="mt-1"><x-filament::input type="number" min="0" step="0.000001" wire:model.live.debounce.300ms="selection.{{ $id }}.price" :disabled="!$canBudget || !$choice['included']" /></x-filament::input.wrapper>
                                @error('selection.'.$id.'.price') <span class="text-red-600">{{ $message }}</span> @enderror
                            </label>
                            <label class="text-xs font-medium text-gray-600 dark:text-gray-300">Descuento (%)
                                <x-filament::input.wrapper><x-filament::input type="number" min="0" max="100" step="0.0001" wire:model.live.debounce.300ms="selection.{{ $id }}.discount_percentage" :disabled="!$canBudget || !$choice['included']" /></x-filament::input.wrapper>
                            </label>
                            <div class="text-xs text-gray-500 dark:text-gray-400">Importe<p class="mt-3 text-sm font-semibold tabular-nums text-gray-950 dark:text-white">{{ $choice['included'] && $amount !== null ? \App\Support\BudgetEditor::formatAmount($amount) . ' ' . $currency : '—' }}</p></div>
                        </div>
                        @include('filament.resources.work-orders.pages.partials.item-breakdown', ['item' => $item, 'currency' => $currency])
                        @if (!$editingBudget && !empty($item['view_url']))
                            <x-filament::link :href="$item['view_url']" class="mt-3 inline-block">Ver mediciones y descompuestos</x-filament::link>
                        @endif
                    </div>
                </div>
            </details>
        @endforeach
        @if ($chapter['children'] !== [])
            <div class="space-y-4 border-l-2 border-amber-300 pl-3 sm:pl-5 dark:border-amber-700">
                @foreach ($chapter['children'] as $child)
                    @include('filament.resources.work-orders.pages.partials.chapter-node', ['chapter' => $child, 'items' => $items, 'currency' => $currency, 'canBudget' => $canBudget, 'editingBudget' => $editingBudget])
                @endforeach
            </div>
        @endif
    </div>
</details>
