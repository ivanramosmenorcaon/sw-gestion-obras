<x-filament-widgets::widget>
    <x-filament::section :heading="__('onboarding.title')" :description="__('onboarding.intro')">
        <div class="space-y-6" x-data="{ expandAll: false }">
            <p class="text-sm text-gray-600 dark:text-gray-300">Perfil actual: {{ $role }}. Los accesos y pendientes se limitan a sus permisos.</p>
            <p>{{ __('onboarding.navigation_help') }}</p>
            @if ($pending)
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($pending as $item)
                        <a href="{{ $item['url'] }}" class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                            <span class="block text-2xl font-semibold text-gray-950 dark:text-white">{{ $item['count'] }}</span>
                            <span class="text-sm text-gray-600 dark:text-gray-300">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
            <p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-500/10 dark:text-amber-200">La emisión fiscal continúa en pruebas. Los estados Enviado/Aceptado de presupuestos registran su gestión comercial: el envío al cliente se realiza por su canal habitual.</p>
            <nav class="flex flex-wrap gap-3" aria-label="Exportaciones para asesoría">
                @foreach ($exports as $link)
                    <x-filament::link :href="$link['url']">{{ $link['label'] }}</x-filament::link>
                @endforeach
            </nav>
            <ol class="flex flex-wrap items-center gap-2" aria-label="{{ __('onboarding.steps_label') }}">
                @foreach (__('onboarding.flow') as $stage)
                    <li class="rounded-full bg-primary-50 px-3 py-1.5 text-sm font-medium text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">
                        {{ $loop->iteration }}. {{ $stage }}
                    </li>
                @endforeach
            </ol>

            @if ($quickLinks)
                <nav class="flex flex-wrap gap-2" aria-label="{{ __('onboarding.quick_links') }}">
                    @foreach ($quickLinks as $link)
                        <x-filament::button :href="$link['url']" tag="a" color="gray" size="sm">
                            {{ $link['label'] }}
                        </x-filament::button>
                    @endforeach
                </nav>
            @endif

            <div class="flex flex-wrap gap-3">
                <x-filament::button color="gray" size="sm" x-on:click="expandAll = true">{{ __('onboarding.expand') }}</x-filament::button>
                <x-filament::button color="gray" size="sm" x-on:click="expandAll = false">{{ __('onboarding.collapse') }}</x-filament::button>
            </div>

            <div class="space-y-3">
                @foreach ($steps as $index => $step)
                    <details class="group rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900" x-bind:open="expandAll" id="guide-step-{{ $index + 1 }}">
                        <summary class="flex cursor-pointer items-start gap-3 rounded-xl px-4 py-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 sm:px-5">
                            <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-gray-100 text-sm font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-200">{{ $index + 1 }}</span>
                            <span class="min-w-0 flex-1 text-sm font-semibold leading-7 text-gray-950 dark:text-white">{{ $step['title'] }}</span>
                            <x-filament::icon icon="heroicon-o-chevron-down" class="mt-1 size-5 shrink-0 text-gray-400 transition-transform group-open:rotate-180" />
                        </summary>
                        <div class="space-y-4 border-t border-gray-100 px-4 py-4 sm:px-5 dark:border-white/10">
                            <p class="text-sm font-medium leading-6 text-primary-700 dark:text-primary-300">{{ $step['path'] }}</p>
                            <ol class="list-decimal space-y-2 ps-5 text-sm leading-6 text-gray-700 dark:text-gray-300">
                                @foreach ($step['instructions'] as $instruction)
                                    <li class="ps-1">{{ $instruction }}</li>
                                @endforeach
                            </ol>
                            @if (isset($step['tip']))
                                <p class="rounded-lg bg-gray-50 px-3 py-3 text-sm leading-6 text-gray-600 dark:bg-white/5 dark:text-gray-300">
                                    <strong class="font-semibold">{{ __('onboarding.tip') }}:</strong> {{ $step['tip'] }}
                                </p>
                            @endif
                            <div class="flex flex-wrap gap-x-4 gap-y-2">
                                @foreach ($step['links'] as $key)
                                    @if (isset($links[$key]))
                                        <x-filament::link :href="$links[$key]['url']">{{ __('onboarding.open', ['label' => $links[$key]['label']]) }}</x-filament::link>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </details>
                @endforeach
            </div>

            <p class="text-sm leading-6 text-gray-500 dark:text-gray-400">{{ __('onboarding.footer') }}</p>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
