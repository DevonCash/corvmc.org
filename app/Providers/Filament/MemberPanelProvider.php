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
            ->profile()
            ->userMenu(false)
            ->login()
            ->registration()
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
            ])
            ->brandLogo(asset('images/cmc-compact-logo.svg'))
            ->brandLogoHeight('3rem')
            ->icons(config('filament-icons', []))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
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
            ->authMiddleware([
                Authenticate::class,
            ])
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn (): string => view('livewire.feedback-button-wrapper')->render()
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn (): string => view('filament.components.activity-toggle-button')->render()
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => ActivitySidebar::render()
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_FOOTER,
                fn (): string => view('filament.components.sidebar-footer')->render()
            )
            ->viteTheme('resources/css/app.css');
    }
}
