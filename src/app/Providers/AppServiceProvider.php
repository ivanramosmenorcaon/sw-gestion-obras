<?php

namespace App\Providers;

use App\Filament\Forms\Components\RecordSelect;
use Filament\Forms\Components\Select;
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
        //
    }
}
