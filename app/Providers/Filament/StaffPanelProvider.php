<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\Staff\Pages\StaffDashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class StaffPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('staff')
            ->path('staff')
            ->login()
            ->font('Lexend')
            ->darkMode()
            ->globalSearch(false)
            ->colors([
                'primary' => Color::Cyan,
                'gray' => [
                    50 => 'oklch(0.98 0.01 65)',
                    100 => 'oklch(0.94 0.015 60)',
                    200 => 'oklch(0.87 0.02 55)',
                    300 => 'oklch(0.78 0.025 50)',
                    400 => 'oklch(0.68 0.03 45)',
                    500 => 'oklch(0.57 0.025 40)',
                    600 => 'oklch(0.47 0.02 35)',
                    700 => 'oklch(0.38 0.015 30)',
                    800 => 'oklch(0.29 0.01 25)',
                    900 => 'oklch(0.21 0.008 20)',
                    950 => 'oklch(0.15 0.005 15)',
                ],
            ])
            ->brandLogo(asset('images/cmc-compact-logo.svg'))
            ->darkModeBrandLogo(asset('images/cmc-compact-logo-dark.svg'))
            ->brandLogoHeight('3rem')
            ->icons(config('filament-icons', []))
            ->discoverResources(in: app_path('Filament/Staff/Resources'), for: 'App\\Filament\\Staff\\Resources')
            ->discoverPages(in: app_path('Filament/Staff/Pages'), for: 'App\\Filament\\Staff\\Pages')
            ->discoverWidgets(in: app_path('Filament/Staff/Widgets'), for: 'App\\Filament\\Staff\\Widgets')
            ->pages([
                StaffDashboard::class,
            ])
            ->databaseNotifications()
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn (): string => view('filament.components.dark-mode-toggle')->render()
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn (): string => view('livewire.feedback-button-wrapper')->render()
            )

            ->authGuard('web')
            ->viteTheme('resources/css/app.css');
    }
}
