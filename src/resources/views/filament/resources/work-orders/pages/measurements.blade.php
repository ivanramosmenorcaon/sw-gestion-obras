<x-filament-panels::page>
    @php
        $included = array_filter($this->selection, fn ($choice) => $choice['included'] ?? false);
        $total = \Brick\Math\BigDecimal::of(0);
        foreach ($included as $choice) {
            if (($amount = \App\Support\BudgetEditor::previewAmount($choice)) !== null) { $total = $total->plus($amount); }
        }
    @endphp
    <div class="space-y-4" x-data="{ search: '', dirty: false, expand(open) { $refs.tree.querySelectorAll('details').forEach(node => node.open = open); } }"
         x-on:input="dirty = true" x-on:beforeunload.window="if (dirty) { $event.preventDefault(); $event.returnValue = ''; }">
        <div class="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div>
                <p class="font-semibold text-gray-950 dark:text-white">{{ $this->getRecord()->project->name }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ count($items) }} partidas · {{ $this->getRecord()->created_at->timezone('Europe/Madrid')->format('d/m/Y H:i') }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $this->getRecord()->measurement_source_name ?: 'Importación anterior sin archivo original archivado' }}</p>
            </div>
            <div class="text-right text-sm">
                <p>{{ count($included) }} partidas seleccionadas para presupuesto</p>
                <p class="text-xl font-semibold tabular-nums">{{ \App\Support\BudgetEditor::formatAmount((string) $total) }} {{ $currency }}</p>
                <p class="text-xs text-gray-500">Total seleccionado sin impuestos</p>
            </div>
        </div>
        <p class="text-sm text-gray-600 dark:text-gray-300">Consulta las mediciones de origen y selecciona lo que quieres presupuestar. Los ajustes de cantidad y precio se aplicarán al nuevo presupuesto; el original se conserva. Las partidas se incorporan a la obra desde un presupuesto aceptado.</p>
        @if ($canBudget)
            <div class="flex flex-wrap items-end gap-3">
                <label class="min-w-0 flex-1 text-sm">Nombre del presupuesto
                    <x-filament::input.wrapper><x-filament::input wire:model.blur="budgetName" /></x-filament::input.wrapper>
                    @error('name') <span role="alert" class="text-red-600">{{ $message }}</span> @enderror
                </label>
                <x-filament::button wire:click="mountAction('createBudget')">Crear presupuesto</x-filament::button>
            </div>
        @endif
        @error('selection') <p role="alert" class="text-red-600">{{ $message }}</p> @enderror
        <div class="grid items-start gap-4 xl:grid-cols-2">
            <section aria-label="Árbol de capítulos y partidas" class="overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                <div class="space-y-3 border-b border-gray-200 p-3 dark:border-gray-700">
                    <h2 class="font-semibold">Capítulos y partidas</h2>
                    <label class="block text-sm">Buscar código o descripción
                        <x-filament::input.wrapper><x-filament::input type="search" x-model="search" x-on:input.stop="if (search) expand(true)" placeholder="Código o descripción…" /></x-filament::input.wrapper>
                    </label>
                    <div class="flex gap-2">
                        <x-filament::button size="sm" color="gray" x-on:click="expand(true)">Desplegar todo</x-filament::button>
                        <x-filament::button size="sm" color="gray" x-on:click="expand(false)">Plegar todo</x-filament::button>
                    </div>
                </div>
                <div x-ref="tree" class="max-h-[70vh] overflow-auto p-2">
                    @forelse ($chapters as $chapter)
                        @include('filament.resources.work-orders.pages.partials.measurement-node')
                    @empty
                        <p class="p-4 text-sm text-gray-500">No hay partidas en esta medición.</p>
                    @endforelse
                </div>
            </section>
            <section aria-label="Detalle de la partida" class="space-y-4 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                @if ($selectedItem)
                    @php
                        $id = $selectedItem['source_line_id'];
                        $choice = $this->selection[$id];
                    @endphp
                    <div wire:key="measurement-detail-{{ $id }}" class="space-y-4">
                        <h2 class="font-semibold">Partida {{ $selectedItem['source_code'] ?? $id }}</h2>
                        <p class="whitespace-pre-line text-sm text-gray-700 dark:text-gray-200">{{ $selectedItem['description'] }}</p>
                        <div class="rounded-lg bg-gray-50 p-3 text-sm dark:bg-gray-800">
                            <p class="font-medium">Medición original: {{ $selectedItem['quantity'] }} {{ $selectedItem['unit'] }}</p>
                            <p>Precio original: {{ $selectedItem['price'] }} {{ $currency }}</p>
                        </div>
                        @if ($canBudget)
                            <fieldset class="space-y-3">
                                <legend class="text-sm font-semibold">Valores para el nuevo presupuesto</legend>
                                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model.live="selection.{{ $id }}.included" /> Incluir esta partida</label>
                                <div class="grid grid-cols-2 gap-3">
                                    @foreach (['quantity' => 'Cantidad', 'unit' => 'Unidad', 'price' => 'Precio unitario', 'discount_percentage' => 'Descuento (%)'] as $field => $label)
                                        <label class="text-sm">{{ $label }}
                                            <x-filament::input.wrapper><x-filament::input wire:model.live.debounce.300ms="selection.{{ $id }}.{{ $field }}" :disabled="!$choice['included']" /></x-filament::input.wrapper>
                                            @error('selection.'.$id.'.'.$field) <span role="alert" class="text-red-600">{{ $message }}</span> @enderror
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endif
                        <div x-data="{ tab: 'measurements' }">
                            <div class="flex gap-2 border-b border-gray-200 pb-2 dark:border-gray-700" role="tablist" aria-label="Datos originales">
                                <button type="button" role="tab" id="tab-measurements" aria-controls="panel-measurements" x-bind:aria-selected="tab === 'measurements'" x-on:click="tab = 'measurements'" class="rounded px-3 py-2 text-sm font-medium" x-bind:class="tab === 'measurements' ? 'bg-amber-100 text-amber-900 dark:bg-amber-900 dark:text-amber-100' : ''">Mediciones ({{ count($selectedItem['measurement_details']) }})</button>
                                <button type="button" role="tab" id="tab-breakdown" aria-controls="panel-breakdown" x-bind:aria-selected="tab === 'breakdown'" x-on:click="tab = 'breakdown'" class="rounded px-3 py-2 text-sm font-medium" x-bind:class="tab === 'breakdown' ? 'bg-amber-100 text-amber-900 dark:bg-amber-900 dark:text-amber-100' : ''">Descompuestos ({{ count($selectedItem['budget_breakdown']) }})</button>
                            </div>
                            <div x-show="tab === 'measurements'" id="panel-measurements" role="tabpanel" aria-labelledby="tab-measurements" class="overflow-x-auto">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-gray-50 dark:bg-gray-800"><tr>@foreach (['Descripción', 'Unidades', 'Largo', 'Ancho', 'Alto', 'Parcial'] as $label)<th scope="col" class="whitespace-nowrap p-2">{{ $label }}</th>@endforeach</tr></thead>
                                    <tbody>
                                        @forelse ($selectedItem['measurement_details'] as $detail)
                                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                                @foreach (['label', 'units', 'length', 'width', 'height', 'subtotal'] as $field)<td class="p-2 tabular-nums">{{ $detail[$field] ?? '—' }}</td>@endforeach
                                            </tr>
                                        @empty
                                            <tr><td colspan="6" class="p-3 text-gray-500">El documento aporta una cantidad total sin desglose de medición.</td></tr>
                                        @endforelse
                                    </tbody>
                                    <tfoot><tr class="font-semibold"><td colspan="5" class="p-2">Total original ({{ $selectedItem['unit'] }})</td><td class="p-2 tabular-nums">{{ $selectedItem['quantity'] }}</td></tr></tfoot>
                                </table>
                            </div>
                            <div x-show="tab === 'breakdown'" x-cloak id="panel-breakdown" role="tabpanel" aria-labelledby="tab-breakdown" class="overflow-x-auto">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-gray-50 dark:bg-gray-800"><tr>@foreach (['Código', 'Padre', 'Recurso', 'Ud.', 'Factor', 'Rendimiento', 'Precio'] as $label)<th scope="col" class="p-2">{{ $label }}</th>@endforeach</tr></thead>
                                    <tbody>
                                        @forelse ($selectedItem['budget_breakdown'] as $detail)
                                            <tr class="border-b border-gray-100 dark:border-gray-800">@foreach (['code', 'parent_code', 'name', 'unit', 'factor', 'quantity', 'price'] as $field)<td class="p-2">{{ $detail[$field] ?? '—' }}</td>@endforeach</tr>
                                        @empty
                                            <tr><td colspan="7" class="p-3 text-gray-500">El documento no aporta descompuestos para esta partida.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @else
                    <p class="text-sm text-gray-500">Selecciona una partida para consultar su medición.</p>
                @endif
            </section>
        </div>
        @if ($this->getRecord()->instructions)
            <details class="text-sm"><summary class="cursor-pointer">Observaciones de la importación</summary><p class="whitespace-pre-line p-3">{{ $this->getRecord()->instructions }}</p></details>
        @endif
    </div>
</x-filament-panels::page>
