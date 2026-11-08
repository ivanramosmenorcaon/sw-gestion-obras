<div class="mt-5 space-y-4 border-t border-gray-100 pt-4 dark:border-gray-800">
    <details open>
        <summary class="cursor-pointer text-sm font-semibold text-gray-950 dark:text-white">Descompuestos de la partida original</summary>
        @if (!empty($item['budget_breakdown']))
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Precios y rendimientos originales de referencia. El importe del presupuesto se calcula con la cantidad y el precio de la partida.</p>
            <div class="mt-3 space-y-3">
                @foreach (app(\App\Support\BudgetBreakdownTree::class)->build($item['budget_breakdown']) as $resourceNode)
                    @include('filament.resources.work-order-lines.pages.partials.budget-node', ['node' => $resourceNode, 'currency' => $currency])
                @endforeach
            </div>
        @else
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Esta partida no tiene recursos descompuestos en el documento original.</p>
        @endif
    </details>
    @if (!empty($item['measurement_details']))
        <details>
            <summary class="cursor-pointer text-sm font-semibold text-gray-950 dark:text-white">Desglose de medición original</summary>
            <div class="mt-3 space-y-3">
                @foreach ($item['measurement_details'] as $measurement)
                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ $measurement['label'] ?? 'Medición' }}</p>
                        <dl class="mt-2 grid grid-cols-2 gap-3 text-xs sm:grid-cols-5">
                            @foreach (['units' => 'Unidades', 'length' => 'Largo', 'width' => 'Ancho', 'height' => 'Alto', 'subtotal' => 'Parcial'] as $field => $label)
                                <div>
                                    <dt class="text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                                    <dd class="mt-1 tabular-nums text-gray-950 dark:text-white">{{ isset($measurement[$field]) ? number_format((float) $measurement[$field], 6, ',', '.') : '—' }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                @endforeach
            </div>
        </details>
    @endif
</div>
