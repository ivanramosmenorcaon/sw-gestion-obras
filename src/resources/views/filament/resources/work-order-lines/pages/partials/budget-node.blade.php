<details
    data-resource-node
    data-resource-key="{{ $node['key'] }}"
    data-resource-depth="{{ $node['depth'] }}"
    class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900"
    @if ($node['depth'] === 1) open @endif
>
    <summary class="cursor-pointer rounded-xl px-4 py-3 marker:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800">
        <div class="inline-flex max-w-full flex-wrap items-center gap-2 align-middle">
            <x-filament::badge :color="$node['color']" :icon="$node['icon']">{{ $node['kind'] }}</x-filament::badge>
            <span class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ $node['code'] }}</span>
            <span class="break-words font-medium text-gray-950 dark:text-white">{{ $node['name'] }}</span>
            <span class="text-xs text-gray-500 dark:text-gray-400">Nivel {{ $node['depth'] }}</span>
        </div>
    </summary>

    <div class="space-y-4 border-t border-gray-100 p-4 dark:border-gray-800">
        <dl class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
            @foreach (['Unidad' => $node['unit'], 'Factor' => number_format($node['factor'], 6, ',', '.'), 'Rendimiento' => number_format($node['quantity'], 6, ',', '.'), 'Precio unitario original' => $node['price'] === null ? 'Sin precio' : number_format($node['price'], 6, ',', '.') . ' ' . $currency] as $label => $value)
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                    <dd class="mt-1 break-words font-medium tabular-nums text-gray-950 dark:text-white">{{ $value ?: '—' }}</dd>
                </div>
            @endforeach
        </dl>
        <p class="text-xs text-gray-500 dark:text-gray-400">Depende de <span class="font-mono">{{ $node['parent_code'] }}</span></p>
        @if ($node['description'] !== '')
            <p class="whitespace-pre-line text-sm leading-relaxed text-gray-600 dark:text-gray-300">{{ $node['description'] }}</p>
        @endif
        @if ($node['children'] !== [])
            <div class="space-y-3 border-l-2 border-amber-300 pl-3 sm:pl-5 dark:border-amber-700">
                @foreach ($node['children'] as $child)
                    @include('filament.resources.work-order-lines.pages.partials.budget-node', ['node' => $child, 'currency' => $currency])
                @endforeach
            </div>
        @endif
    </div>
</details>
