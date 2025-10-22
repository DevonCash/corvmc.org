<?php

namespace App\Providers\Filament;

use App\Filament\Components\ActivitySidebar;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use App\Filament\Widgets\ActivityFeedWidget;
use App\Filament\Widgets\MyBandsWidget;
use App\Filament\Widgets\TodayReservationsWidget;
use App\Filament\Widgets\UpcomingEventsWidget;
use App\Filament\Widgets\QuickActionsWidget;
use App\Filament\Widgets\UserSummaryWidget;
use Filament\Actions\Action;
use Filament\Pages\Dashboard;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class MemberPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('member')
            ->path('member')
            ->userMenu(false)
            ->login()
            ->registration(\App\Filament\Pages\Auth\Register::class)
            ->passwordReset()
            ->emailVerification()
            ->font('Lexend')
            ->darkMode()
            ->colors([
                'primary' => [
                    50 => 'oklch(0.98 0.04 43)',
                    100 => 'oklch(0.95 0.08 43)',
                    200 => 'oklch(0.90 0.13 43)',
                    300 => 'oklch(0.83 0.16 43)',
                    400 => 'oklch(0.75 0.17 43)',
                    500 => 'oklch(0.67 0.18 43)', // CMC Brand Orange
                    600 => 'oklch(0.59 0.16 43)',
                    700 => 'oklch(0.51 0.14 43)',
                    800 => 'oklch(0.43 0.12 43)',
                    900 => 'oklch(0.35 0.10 43)',
                    950 => 'oklch(0.27 0.08 43)',
                ],
                'secondary' => [
                    50 => 'oklch(0.95 0.02 250)',
                    100 => 'oklch(0.90 0.03 250)',
                    200 => 'oklch(0.80 0.05 250)',
                    300 => 'oklch(0.70 0.06 250)',
                    400 => 'oklch(0.50 0.07 250)',
                    500 => 'oklch(0.25 0.09 250)', // CMC Blue
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
                    300 => 'oklch(0.85 0.05 200)', // CMC Light Blue
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
                    300 => 'oklch(0.90 0.08 85)', // CMC Yellow
                    400 => 'oklch(0.85 0.07 85)',
                    500 => 'oklch(0.80 0.06 85)',
                    600 => 'oklch(0.75 0.05 85)',
                    700 => 'oklch(0.70 0.04 85)',
                    800 => 'oklch(0.65 0.04 85)',
                    900 => 'oklch(0.60 0.04 85)',
                    950 => 'oklch(0.55 0.04 85)',
                ],
                'gray' => [
                    50 => 'oklch(0.98 0.01 65)',   // warm cream white
                    100 => 'oklch(0.94 0.015 60)', // light parchment
                    200 => 'oklch(0.87 0.02 55)',  // aged paper tone
                    300 => 'oklch(0.78 0.025 50)', // better contrast step
                    400 => 'oklch(0.68 0.03 45)',  // more distinct
                    500 => 'oklch(0.57 0.025 40)', // neutral middle
                    600 => 'oklch(0.47 0.02 35)',  // darker contrast
                    700 => 'oklch(0.38 0.015 30)', // good for text
                    800 => 'oklch(0.29 0.01 25)',  // strong contrast
                    900 => 'oklch(0.21 0.008 20)', // very dark
                    950 => 'oklch(0.15 0.005 15)', // almost black
                ],
            ])
            ->brandLogo(asset('images/cmc-compact-logo.svg'))
            ->darkModeBrandLogo(asset('images/cmc-compact-logo-dark.svg'))
            ->brandLogoHeight('3rem')
            ->icons(config('filament-icons', []))
            ->resources([
                \App\Filament\Resources\MemberProfiles\MemberProfileResource::class,
                \App\Filament\Resources\Bands\BandResource::class,
                \App\Filament\Resources\CommunityEvents\CommunityEventResource::class,
                \App\Filament\Resources\Reservations\ReservationResource::class,
                \App\Filament\Resources\Equipment\EquipmentResource::class,
                \App\Filament\Resources\Equipment\EquipmentLoans\EquipmentLoanResource::class,
            ])
            ->pages([
                Dashboard::class,
                \App\Filament\Pages\MyProfile::class,
                \App\Filament\Pages\MyAccount::class,
                \App\Filament\Pages\MyMembership::class,
            ])
            ->databaseNotifications()
            ->widgets([
                UserSummaryWidget::class,
                ActivityFeedWidget::class,
                QuickActionsWidget::class,
                UpcomingEventsWidget::class,
                MyBandsWidget::class,
                TodayReservationsWidget::class,
            ])

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
            ->globalSearch(false)
            ->authMiddleware([
                Authenticate::class,
            ])
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn(): string => view('filament.components.dark-mode-toggle')->render()
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn(): string => view('livewire.feedback-button-wrapper')->render()
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn(): string => view('filament.components.activity-toggle-button')->render()
            )

            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn(): string => ActivitySidebar::render()
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_FOOTER,
                fn(): string => view('filament.components.sidebar-footer')->render()
            )
            ->viteTheme('resources/css/app.css');
    }
}
