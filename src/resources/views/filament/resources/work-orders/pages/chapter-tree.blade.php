<x-filament-panels::page>
    @php
        $total = \Brick\Math\BigDecimal::of(0);
        $includedCount = 0;
        foreach ($items as $item) {
            $choice = $this->selection[$item['source_line_id']] ?? [];
            if ($choice['included'] ?? false) {
                $includedCount++;
                $amount = \App\Support\BudgetEditor::previewAmount($choice);
                if ($amount !== null) { $total = $total->plus($amount); }
            }
        }
        if ($editingBudget && $this->getRecord()->status === 'draft') {
            foreach ($this->manualLines as $line) {
                if (filled($line['description'] ?? null)) {
                    $amount = \App\Support\BudgetEditor::previewAmount(['quantity' => \App\Support\BudgetEditor::decimal($line['quantity']), 'price' => \App\Support\BudgetEditor::decimal($line['price']), 'discount_percentage' => $line['discount_percentage'] ?? '0']);
                    if ($amount !== null) { $total = $total->plus($amount); $includedCount++; }
                }
            }
        }
    @endphp
    <div class="space-y-6" x-on:input="dirty = true" x-on:budget-saved.window="dirty = false" x-on:beforeunload.window="if (dirty) { $event.preventDefault(); $event.returnValue = ''; }" x-data="{ dirty: false, expand(open) { $refs.tree.querySelectorAll('details').forEach(node => node.open = open); } }">
        <div class="rounded-2xl bg-gray-950 p-6 text-white dark:bg-gray-800">
            <p class="text-xs text-gray-300">{{ $this->getRecord()->project->name }} · {{ $this->getRecord()->reference }}</p>
            <h2 class="mt-2 text-xl font-semibold">{{ $editingBudget ? 'Edición del presupuesto' : 'Capítulos → Subcapítulos → Partidas' }}</h2>
            <p class="mt-3 text-sm text-gray-300">{{ $editingBudget ? 'Excluya partidas o capítulos para eliminarlos de este presupuesto al guardar. Sus mediciones de origen se conservarán.' : 'Seleccione lo que desea presupuestar y ajuste cantidades y precios. Los cambios de esta pantalla solo se aplicarán al nuevo presupuesto; las mediciones originales se conservarán.' }}</p>
            <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-white/15 pt-5">
                <span>{{ $includedCount }} de {{ count($items) }} partidas incluidas</span>
                <span class="text-2xl font-semibold tabular-nums">{{ \App\Support\BudgetEditor::formatAmount((string) $total) }} {{ $currency }}</span>
            </div>
            <p class="mt-1 text-right text-xs text-gray-400">Total sin impuestos · importes por partida redondeados a 2 decimales</p>
        </div>
        @if ($canBudget)
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                Nombre del presupuesto
                <x-filament::input.wrapper class="mt-2"><x-filament::input wire:model.blur="budgetName" /></x-filament::input.wrapper>
                @error('name') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </label>
        @endif
        @if ($editingBudget)
            <p x-show="dirty" role="status">Hay cambios sin guardar.</p>
            <p>Estado: {{ $this->getRecord()->status }} · Revisión {{ $this->getRecord()->revision }}. Guarde antes de imprimir.</p>
            @if ($this->getRecord()->status === 'draft')
            <x-filament::section heading="Condiciones comerciales">
                @foreach (['terms' => 'Condiciones y forma de pago', 'exclusions' => 'Exclusiones', 'valid_until' => 'Válido hasta'] as $field => $label)
                    <label class="block">{{ $label }}<input class="w-full rounded border p-2 dark:bg-gray-900" type="{{ $field === 'valid_until' ? 'date' : 'text' }}" wire:model="commercialTerms.{{ $field }}" /></label>
                    @error($field) <p role="alert">{{ $message }}</p> @enderror
                @endforeach
            </x-filament::section>
            <x-filament::section heading="Líneas manuales">
                <p>Descripción, unidad, cantidad y precio. Tab al final añade otra línea. Puede quitar filas con «Eliminar línea»; confirme los cambios con «Guardar presupuesto».</p>
                @foreach ($this->manualLines as $index => $line)
                    <div class="grid gap-3 md:grid-cols-4" wire:key="manual-{{ $line['editor_key'] ?? $index }}">
                        @foreach (['description' => 'Descripción y detalles', 'unit' => 'Unidad', 'quantity' => 'Cantidad', 'price' => 'Precio', 'discount_percentage' => 'Descuento (%)'] as $field => $label)
                            <label>{{ $label }}
                                <input class="w-full rounded border p-2 dark:bg-gray-900" wire:model.live.debounce.300ms="manualLines.{{ $index }}.{{ $field }}"
                                    @if ($field === 'discount_percentage' && $loop->parent->last)
                                        x-on:keydown.tab="if (!$event.shiftKey) { $event.preventDefault(); await $wire.addManualLine(); $nextTick(() => document.querySelectorAll('[data-manual-description]')[{{ $index + 1 }}]?.focus()); }"
                                    @endif
                                    @if ($field === 'description') data-manual-description @endif
                                />
                                @error("lines.$index.$field") <span role="alert">{{ $message }}</span> @enderror
                            </label>
                        @endforeach
                        <x-filament::button type="button" color="danger" size="sm" wire:click="removeManualLine({{ $index }})">
                            Eliminar línea
                        </x-filament::button>
                    </div>
                @endforeach
                <x-filament::button wire:click="addManualLine">Añadir línea</x-filament::button>

            </x-filament::section>
            @endif
        @endif
        @error('selection') <p role="alert" class="rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">{{ $message }}</p> @enderror
        <div class="flex flex-wrap gap-2">
            <x-filament::button color="gray" size="sm" x-on:click="expand(true)">Desplegar todo</x-filament::button>
            <x-filament::button color="gray" size="sm" x-on:click="expand(false)">Plegar todo</x-filament::button>
        </div>
        <div x-ref="tree" class="space-y-4">
            @forelse ($chapters as $chapter)
                @include('filament.resources.work-orders.pages.partials.chapter-node', ['chapter' => $chapter, 'items' => $items, 'currency' => $currency, 'canBudget' => $canBudget, 'editingBudget' => $editingBudget])
            @empty
                <p class="rounded-xl border border-dashed border-gray-300 p-6 text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">No hay partidas cargadas. Puede crear un presupuesto desde Presupuestos y añadir líneas manuales.</p>
            @endforelse
        </div>
        @if ($canBudget)
            <div class="flex justify-end">
                @if ($editingBudget && $this->getRecord()->status === 'draft')
                    <x-filament::button wire:click="mountAction('saveBudget')">Guardar presupuesto</x-filament::button>
                @elseif (!$editingBudget)
                    <x-filament::button wire:click="mountAction('createBudget')">Crear presupuesto</x-filament::button>
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>
