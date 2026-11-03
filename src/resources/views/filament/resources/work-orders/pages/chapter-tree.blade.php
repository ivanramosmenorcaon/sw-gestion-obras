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
    @endphp
    <div class="space-y-6" x-data="{ expand(open) { $refs.tree.querySelectorAll('details').forEach(node => node.open = open); } }">
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
        @error('selection') <p role="alert" class="rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">{{ $message }}</p> @enderror
        <div class="flex flex-wrap gap-2">
            <x-filament::button color="gray" size="sm" x-on:click="expand(true)">Desplegar todo</x-filament::button>
            <x-filament::button color="gray" size="sm" x-on:click="expand(false)">Plegar todo</x-filament::button>
        </div>
        <div x-ref="tree" class="space-y-4">
            @forelse ($chapters as $chapter)
                @include('filament.resources.work-orders.pages.partials.chapter-node', ['chapter' => $chapter, 'items' => $items, 'currency' => $currency, 'canBudget' => $canBudget, 'editingBudget' => $editingBudget])
            @empty
                <p class="rounded-xl border border-dashed border-gray-300 p-6 text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">Esta orden no tiene partidas. Añada o importe mediciones antes de crear un presupuesto.</p>
            @endforelse
        </div>
        @if ($canBudget)
            <div class="flex justify-end">
                @if ($editingBudget)
                    <x-filament::button wire:click="mountAction('saveBudget')">Guardar presupuesto</x-filament::button>
                @else
                    <x-filament::button wire:click="mountAction('createBudget')">Crear presupuesto</x-filament::button>
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>
