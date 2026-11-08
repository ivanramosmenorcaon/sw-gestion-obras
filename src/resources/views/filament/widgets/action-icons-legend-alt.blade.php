<x-filament-widgets::widget>
    <x-slot name="heading">
        {{ __('Leyenda de Acciones') }}
    </x-slot>

    <div class="space-y-3">
        @php
            $icons = $this->getActions();
            $chunks = collect($icons)->chunk(2);
        @endphp

        @foreach ($chunks as $row)
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach ($row as $actionName => $actionConfig)
                    <div class="flex items-start gap-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-primary-300 dark:hover:border-primary-700 transition-colors">
                        <div class="flex-shrink-0 pt-0.5">
                            <svg class="w-5 h-5 text-primary-500" fill="currentColor" viewBox="0 0 24 24">
                                <!-- Icono genérico, reemplazar con dinámico si es posible -->
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $actionConfig['tooltip'] }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                {{ str_replace('_', ' ', $actionName) }}
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>

    <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
        <p class="text-xs text-blue-800 dark:text-blue-300">
            <strong>💡 Tip:</strong> Estos iconos son configurables en <code>config/action-icons.php</code>
        </p>
    </div>
</x-filament-widgets::widget>
