<x-filament-widgets::widget>
    <x-slot name="heading">
        {{ __('Leyenda de Acciones') }}
    </x-slot>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach ($this->getActions() as $actionName => $actionConfig)
            <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="flex-shrink-0">
                    <x-dynamic-component
                        :component="$actionConfig['icon']"
                        class="w-6 h-6 text-primary-500"
                    />
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                        {{ $actionConfig['tooltip'] }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $actionName }}
                    </p>
                </div>
            </div>
        @endforeach
    </div>
</x-filament-widgets::widget>
