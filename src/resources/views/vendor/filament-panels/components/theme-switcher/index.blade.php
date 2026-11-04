@php
    use Filament\Support\Icons\Heroicon;

    $thinLabel = __('filament.theme_switcher.thin');
@endphp

<div
    x-data="{ theme: null, thin: false }"
    x-init="
        $watch('theme', () => {
            $dispatch('theme-changed', theme)
        });

        $watch('thin', () => {
            localStorage.setItem('filament:theme:thin', thin ? '1' : '0');
            document.documentElement.classList.toggle('fi-thin', thin);
        });

        theme = localStorage.getItem('filament:theme:mode') || @js(filament()->getDefaultThemeMode()->value);
        thin = localStorage.getItem('filament:theme:thin') === '1';
    "
    role="group"
    aria-label="{{ __('filament-panels::layout.actions.theme_switcher.label') }}"
    class="fi-theme-switcher"
>
    <x-filament-panels::theme-switcher.button
        :icon="Heroicon::Sun"
        theme="light"
    />

    <x-filament-panels::theme-switcher.button
        :icon="Heroicon::Moon"
        theme="dark"
    />

    <x-filament-panels::theme-switcher.button
        :icon="Heroicon::ComputerDesktop"
        theme="system"
    />

    <button
        aria-label="{{ $thinLabel }}"
        role="menuitemcheckbox"
        tabindex="-1"
        type="button"
        x-on:click="thin = ! thin"
        x-tooltip="{
            content: @js($thinLabel),
            theme: $store.theme,
        }"
        x-bind:aria-checked="thin ? 'true' : 'false'"
        x-bind:class="{ 'fi-active': thin }"
        class="fi-theme-switcher-btn"
    >
        {{ \Filament\Support\generate_icon_html(Heroicon::ArrowsPointingIn) }}
    </button>
</div>
