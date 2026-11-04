<?php

namespace App\Providers;

use App\Filament\Forms\Components\RecordSelect;
use Filament\Forms\Components\Select;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(Select::class, RecordSelect::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): string => Blade::render(<<<'HTML'
                <script>
                    const loadThinTheme = () => document.documentElement.classList.toggle(
                        'fi-thin',
                        localStorage.getItem('filament:theme:thin') === '1',
                    )

                    loadThinTheme()

                    document.addEventListener('livewire:navigated', loadThinTheme)
                </script>
                HTML),
        );
    }
}
