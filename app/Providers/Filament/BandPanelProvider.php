<?php

namespace App\Providers\Filament;

use App\Filament\Band\Pages\BandDashboard;
use App\Filament\Band\Pages\EditBandProfile;
use App\Filament\Band\Resources\BandMembersResource;
use App\Filament\Band\Resources\BandProductionsResource;
use App\Filament\Band\Resources\BandReservationsResource;
use App\Filament\Band\Pages\Tenancy\RegisterBand;
use CorvMC\Bands\Http\Middleware\EnsureActiveBandMembership;
use CorvMC\Bands\Models\Band;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class BandPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('band')
            ->path('band')
            ->tenant(Band::class, slugAttribute: 'slug')
            ->tenantRegistration(RegisterBand::class)
            ->font('Lexend')
            ->darkMode()
            ->login()
            ->breadcrumbs(false)
            ->colors([
                'primary' => [
                    50 => 'oklch(0.98 0.04 250)',
                    100 => 'oklch(0.95 0.08 250)',
                    200 => 'oklch(0.90 0.13 250)',
                    300 => 'oklch(0.83 0.16 250)',
                    400 => 'oklch(0.75 0.17 250)',
                    500 => 'oklch(0.67 0.18 250)', // CMC Blue for Band Panel
                    600 => 'oklch(0.59 0.16 250)',
                    700 => 'oklch(0.51 0.14 250)',
                    800 => 'oklch(0.43 0.12 250)',
                    900 => 'oklch(0.35 0.10 250)',
                    950 => 'oklch(0.27 0.08 250)',
                ],
                'secondary' => [
                    50 => 'oklch(0.95 0.02 250)',
                    100 => 'oklch(0.90 0.03 250)',
                    200 => 'oklch(0.80 0.05 250)',
                    300 => 'oklch(0.70 0.06 250)',
                    400 => 'oklch(0.50 0.07 250)',
                    500 => 'oklch(0.25 0.09 250)',
                    600 => 'oklch(0.22 0.08 250)',
                    700 => 'oklch(0.19 0.07 250)',
                    800 => 'oklch(0.16 0.06 250)',
                    900 => 'oklch(0.13 0.05 250)',
                    950 => 'oklch(0.10 0.04 250)',
                ],
                'accent' => [
                    50 => 'oklch(0.95 0.02 200)',
                    100 => 'oklch(0.92 0.03 200)',
                    200 => 'oklch(0.88 0.04 200)',
                    300 => 'oklch(0.85 0.05 200)',
                    400 => 'oklch(0.80 0.04 200)',
                    500 => 'oklch(0.75 0.04 200)',
                    600 => 'oklch(0.70 0.04 200)',
                    700 => 'oklch(0.65 0.04 200)',
                    800 => 'oklch(0.60 0.04 200)',
                    900 => 'oklch(0.55 0.04 200)',
                    950 => 'oklch(0.50 0.04 200)',
                ],
                'warning' => [
                    50 => 'oklch(0.98 0.02 85)',
                    100 => 'oklch(0.95 0.04 85)',
                    200 => 'oklch(0.92 0.06 85)',
                    300 => 'oklch(0.90 0.08 85)',
                    400 => 'oklch(0.85 0.07 85)',
                    500 => 'oklch(0.80 0.06 85)',
                    600 => 'oklch(0.75 0.05 85)',
                    700 => 'oklch(0.70 0.04 85)',
                    800 => 'oklch(0.65 0.04 85)',
                    900 => 'oklch(0.60 0.04 85)',
                    950 => 'oklch(0.55 0.04 85)',
                ],
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
            ->resources([
                BandMembersResource::class,
                BandReservationsResource::class,
                // BandProductionsResource::class,
            ])
            ->pages([
                BandDashboard::class,
                EditBandProfile::class,
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
            ->tenantMiddleware([
                EnsureActiveBandMembership::class,
            ], isPersistent: true)
            ->globalSearch(false)
            ->authMiddleware([
                Authenticate::class,
            ])
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn(): string => view('filament.components.dark-mode-toggle')->render()
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn(): string => view('livewire.feedback-button-wrapper')->render()
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_FOOTER,
                fn (): string => view('filament.components.sidebar-footer')->render()
            )
            ->viteTheme('resources/css/app.css');
    }
}
