<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-2xl bg-gray-950 p-5 text-white sm:p-8 dark:bg-gray-800">
            <div class="flex flex-wrap items-center gap-2 text-xs text-gray-300">
                <span>Obra: {{ $line->project->name }}</span>
                <span aria-hidden="true">/</span>
                <span>Orden: {{ $line->workOrder->reference }} · {{ $line->workOrder->name }}</span>
                @foreach ($chapters as $chapter)
                    <span aria-hidden="true">/</span>
                    <span>Capítulo {{ $chapter->code }} · {{ $chapter->name }}</span>
                @endforeach
            </div>
            <div class="mt-5 flex flex-wrap items-center gap-3">
                <x-filament::badge color="primary" icon="heroicon-o-rectangle-stack">Partida</x-filament::badge>
                <span class="font-mono text-sm text-amber-300">{{ $line->source_code ?? 'Sin código de origen' }}</span>
            </div>
            <h2 class="mt-3 text-xl leading-snug font-semibold sm:text-2xl">{{ $title }}</h2>
            <dl class="mt-6 grid grid-cols-2 gap-5 border-t border-white/15 pt-5 lg:grid-cols-4">
                @foreach (['Medición prevista' => number_format((float) $line->planned_quantity, 6, ',', '.') . ' ' . $line->unit_label, 'Venta unitaria estimada' => number_format((float) $line->estimated_unit_sale_price, 6, ',', '.') . ' ' . $currency, 'Importe estimado de venta' => number_format((float) $line->planned_quantity * (float) $line->estimated_unit_sale_price, 2, ',', '.') . ' ' . $currency, 'Descompuesto' => $resourceCount . ' recursos · ' . $levelCount . ' niveles'] as $label => $value)
                    <div>
                        <dt class="text-xs text-gray-300">{{ $label }}</dt>
                        <dd class="mt-1 text-base font-semibold tabular-nums sm:text-lg">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        <x-filament::section heading="Descripción y datos de la partida" collapsible>
            <p class="whitespace-pre-line text-sm leading-relaxed text-gray-700 dark:text-gray-200">{{ $line->description }}</p>
            <dl class="mt-5 grid grid-cols-2 gap-4 border-t border-gray-100 pt-5 text-sm lg:grid-cols-4 dark:border-gray-800">
                @foreach (['Posición' => $line->position, 'Tipo de partida' => __('business.options.' . $line->type), 'Unidad' => $line->unit->name, 'Unidad base' => $line->baseUnit->name, 'Factor de conversión' => $line->base_unit_factor, 'Coste unitario estimado' => number_format((float) $line->estimated_unit_cost, 6, ',', '.') . ' ' . $currency, 'Producto' => $line->product?->name, 'Presentación' => $line->productUnit?->name, 'Fase' => $line->workOrderPhase?->name] as $label => $value)
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                        <dd class="mt-1 font-medium text-gray-950 dark:text-white">{{ $value ?? 'Sin asignar' }}</dd>
                    </div>
                @endforeach
            </dl>
            @if ($line->notes)
                <p class="mt-5 whitespace-pre-line rounded-lg bg-gray-50 p-3 text-sm text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $line->notes }}</p>
            @endif
        </x-filament::section>

        <x-filament::section heading="Árbol de descomposición">
            <p class="text-sm text-gray-600 dark:text-gray-300">Los capítulos agrupan partidas. Cada partida puede descomponerse en auxiliares, materiales, mano de obra y maquinaria. Abre cada nodo para consultar todos sus datos.</p>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Los precios y rendimientos de los recursos son los originales del BC3. No se suman directamente como coste de la partida: los porcentajes y auxiliares compuestos requieren su propio cálculo.</p>
            @if ($nodes !== [])
                <div class="mt-5 space-y-5" x-data="{
                    query: '',
                    noMatch: false,
                    filter() {
                        const needle = this.query.trim().toLocaleLowerCase('es');
                        this.$refs.tree.querySelectorAll('[data-resource-node]').forEach(node => {
                            const match = node.textContent.toLocaleLowerCase('es').includes(needle);
                            node.hidden = !match;
                            if (needle && match) node.open = true;
                        });
                        this.noMatch = !Array.from(this.$refs.tree.querySelectorAll('[data-resource-node]')).some(node => !node.hidden);
                    },
                    expand(open) {
                        this.$refs.tree.querySelectorAll('[data-resource-node]').forEach(node => node.open = open);
                    }
                }">
                    <div class="flex flex-wrap items-end gap-3">
                        <label class="min-w-48 flex-1 text-xs font-medium text-gray-600 dark:text-gray-300">
                            Buscar un recurso
                            <x-filament::input.wrapper class="mt-1">
                                <x-filament::input type="search" x-model="query" x-on:input.debounce.200ms="filter()" placeholder="Código, nombre o descripción…" />
                            </x-filament::input.wrapper>
                        </label>
                        <x-filament::button color="gray" size="sm" x-on:click="expand(true)">Desplegar todo</x-filament::button>
                        <x-filament::button color="gray" size="sm" x-on:click="expand(false)">Plegar todo</x-filament::button>
                    </div>
                    <div x-ref="tree" class="space-y-3 border-l-2 border-amber-300 pl-3 sm:pl-5 dark:border-amber-700">
                        @foreach ($nodes as $node)
                            @include('filament.resources.work-order-lines.pages.partials.budget-node', ['node' => $node, 'currency' => $currency])
                        @endforeach
                    </div>
                    <p x-show="noMatch" x-cloak role="status" class="text-sm text-gray-500 dark:text-gray-400">No hay recursos que coincidan con la búsqueda.</p>
                </div>
            @else
                <div class="mt-5 rounded-xl border border-dashed border-gray-300 p-6 text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">Esta partida no tiene recursos descompuestos. Puedes consultar su descripción, cantidades y mediciones en esta página.</div>
            @endif
        </x-filament::section>

        <x-filament::section heading="Desglose de mediciones" collapsible>
            @if ($line->measurement_details)
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-gray-200 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            <tr>
                                @foreach (['Descripción', 'Unidades', 'Largo', 'Ancho', 'Alto', 'Parcial', 'Origen'] as $heading)
                                    <th scope="col" class="px-3 py-3 font-medium">{{ $heading }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($line->measurement_details as $measurement)
                                <tr class="text-gray-700 dark:text-gray-200">
                                    <td class="min-w-48 px-3 py-3">{{ $measurement['label'] ?: '—' }}</td>
                                    @foreach (['units', 'length', 'width', 'height', 'subtotal'] as $field)
                                        <td @class(['whitespace-nowrap px-3 py-3 text-right tabular-nums', 'text-red-600 dark:text-red-400' => ($measurement[$field] ?? 0) < 0])>{{ isset($measurement[$field]) ? number_format((float) $measurement[$field], 6, ',', '.') : '—' }}</td>
                                    @endforeach
                                    <td class="px-3 py-3 text-xs text-gray-500 dark:text-gray-400">{{ $measurement['source_row'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t-2 border-gray-200 font-semibold text-gray-950 dark:border-gray-700 dark:text-white">
                            <tr><th scope="row" colspan="5" class="px-3 py-4">Medición total de la partida</th><td class="whitespace-nowrap px-3 py-4 text-right tabular-nums">{{ number_format((float) $line->planned_quantity, 6, ',', '.') }}</td><td class="px-3 py-4">{{ $line->unit_label }}</td></tr>
                        </tfoot>
                    </table>
                </div>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">Sin líneas de medición desglosadas. La cantidad prevista de esta partida es {{ number_format((float) $line->planned_quantity, 6, ',', '.') }} {{ $line->unit_label }}.</p>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
