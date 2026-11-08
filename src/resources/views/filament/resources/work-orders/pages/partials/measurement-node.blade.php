@php
    $subtotal = \Brick\Math\BigDecimal::of(0);
    $allIncluded = true;
    foreach ($chapter['item_keys'] as $index) {
        $choice = $this->selection[$items[$index]['source_line_id']] ?? [];
        $allIncluded = $allIncluded && ($choice['included'] ?? false);
        if (($choice['included'] ?? false) && ($amount = \App\Support\BudgetEditor::previewAmount($choice)) !== null) { $subtotal = $subtotal->plus($amount); }
    }
@endphp
<details open data-chapter="{{ $chapter['key'] }}" x-show="!search || $el.textContent.toLocaleLowerCase().includes(search.toLocaleLowerCase())" class="border-l border-gray-200 dark:border-gray-700">
    <summary class="cursor-pointer bg-gray-50 px-3 py-2 text-sm dark:bg-gray-800">
        <span class="font-semibold">Capítulo {{ $chapter['code'] }} · {{ $chapter['name'] }}</span>
        <span class="text-xs text-gray-500">{{ count($chapter['item_keys']) }} partidas · {{ \App\Support\BudgetEditor::formatAmount((string) $subtotal) }} {{ $currency }}</span>
    </summary>
    <div class="pl-3">
        @if ($canBudget)
            <label class="flex items-center gap-2 p-2 text-xs"><input type="checkbox" @checked($allIncluded) wire:change="toggleChapter('{{ $chapter['key'] }}', $event.target.checked)" /> Incluir capítulo y subcapítulos</label>
        @endif
        @foreach ($chapter['items'] as $index)
            @php $item = $items[$index]; $id = $item['source_line_id']; @endphp
            <div wire:key="measurement-row-{{ $id }}" x-show="!search || $el.textContent.toLocaleLowerCase().includes(search.toLocaleLowerCase())" @class(['flex items-center gap-2 border-b border-gray-100 px-2 py-1 dark:border-gray-800', 'bg-amber-50 dark:bg-amber-950' => $this->selectedLineId === $id])>
                @if ($canBudget)
                    <input type="checkbox" wire:model.live="selection.{{ $id }}.included" aria-label="Incluir partida {{ $item['source_code'] }}" />
                @endif
                <button type="button" wire:click="selectLine({{ $id }})" aria-pressed="{{ $this->selectedLineId === $id ? 'true' : 'false' }}" class="grid min-w-0 flex-1 grid-cols-2 items-start gap-2 py-2 text-left text-xs">
                    <span>
                        <span class="font-mono font-semibold text-amber-700 dark:text-amber-400">{{ $item['source_code'] ?? $id }}</span>
                        <span class="block">{{ strtok($item['description'], "\n") ?: $item['description'] }}</span>
                    </span>
                    <span class="text-right tabular-nums text-gray-500 dark:text-gray-400">
                        <span class="block">{{ $item['quantity'] }} {{ $item['unit'] }}</span>
                        <span class="block">{{ $item['price'] }} {{ $currency }}/{{ $item['unit'] }}</span>
                        <span class="block font-semibold">{{ \App\Support\BudgetEditor::formatAmount(\App\Support\BudgetEditor::amount($item)) }} {{ $currency }}</span>
                    </span>
                </button>
            </div>
        @endforeach
        @foreach ($chapter['children'] as $child)
            @include('filament.resources.work-orders.pages.partials.measurement-node', ['chapter' => $child])
        @endforeach
    </div>
</details>
