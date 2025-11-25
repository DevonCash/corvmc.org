<?php

namespace App\Filament\Kiosk\Pages;

use BackedEnum;
use Filament\Pages\Dashboard;
use Filament\Pages\Page;
use Filament\Panel;

class KioskDashboard extends Dashboard
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $title = '';

    protected static ?string $navigationLabel = 'Home';

    protected static ?int $navigationSort = -100;


    public static function getRoutePath(Panel $panel): string
    {
        return '/';
    }

    public function getWidgets(): array
    {
        return [
            \App\Filament\Kiosk\Widgets\QuickActionsWidget::class,
            \App\Filament\Kiosk\Widgets\CurrentlyCheckedInWidget::class,
            \App\Filament\Kiosk\Widgets\TodaysScheduleWidget::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 2;
    }
}
