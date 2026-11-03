<?php

namespace App\Providers\Filament;

use Asmit\ResizedColumn\ResizedColumnPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->databaseTransactions()
            ->navigationGroups([
                NavigationGroup::make()->label(fn (): string => __('business.groups.works')),
                NavigationGroup::make()->label(fn (): string => __('business.groups.contacts')),
                NavigationGroup::make()->label(fn (): string => __('business.groups.catalog')),
                NavigationGroup::make()->label(fn (): string => __('business.groups.team')),
                NavigationGroup::make()->label(fn (): string => __('fiscal.group')),
                NavigationGroup::make()->label(fn (): string => __('business.groups.settings')),
            ])
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth('full')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->databaseNotifications()
            ->databaseNotificationsPollingInterval('60s')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                // AccountWidget::class,
                // FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugins([
            ResizedColumnPlugin::make()
                ->preserveOnDB(), // optional — database persistence
        ]);

    }
}
