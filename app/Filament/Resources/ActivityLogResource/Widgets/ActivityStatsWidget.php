<?php

namespace App\Filament\Resources\ActivityLogResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Spatie\Activitylog\Models\Activity;

class ActivityStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalActivities = Activity::count();
        $todayActivities = Activity::whereDate('created_at', today())->count();
        $thisWeekActivities = Activity::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count();
        $thisMonthActivities = Activity::whereMonth('created_at', now()->month)->count();

        return [
            Stat::make('Total Activities', number_format($totalActivities))
                ->description('All time activity')
                ->descriptionIcon('tabler-activity')
                ->color('primary'),

            Stat::make('Today', number_format($todayActivities))
                ->description('Activities today')
                ->descriptionIcon('tabler-calendar-event')
                ->color($todayActivities > 0 ? 'success' : 'gray'),

            Stat::make('This Week', number_format($thisWeekActivities))
                ->description('Activities this week')
                ->descriptionIcon('tabler-calendar-week')
                ->color($thisWeekActivities > 0 ? 'info' : 'gray'),

            Stat::make('This Month', number_format($thisMonthActivities))
                ->description('Activities this month')
                ->descriptionIcon('tabler-calendar-month')
                ->color($thisMonthActivities > 0 ? 'warning' : 'gray'),
        ];
    }
}
