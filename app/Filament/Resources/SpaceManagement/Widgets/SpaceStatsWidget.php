<?php

namespace App\Filament\Resources\SpaceManagement\Widgets;

use App\Models\Production;
use App\Models\Reservation;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SpaceStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $pending = Reservation::where('status', 'pending')->count();
        $unpaid = Reservation::where('payment_status', 'unpaid')
            ->where('status', '!=', 'cancelled')
            ->where('cost', '>', 0)
            ->count();

        // This week's reservations
        $weekReservations = Reservation::whereBetween('reserved_at', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ])->where('status', '!=', 'cancelled');

        $weekHours = $weekReservations->sum('hours_used');
        $weekRevenue = $weekReservations->get()->sum(fn($r) => $r->cost->getMinorAmount()->toInt()) / 100;

        // This week's productions using space
        $weekProductions = Production::whereBetween('start_time', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ])->get()->filter(fn($p) => $p->usesPracticeSpace());

        $weekProductionHours = $weekProductions->sum(function ($p) {
            return $p->start_time->diffInHours($p->end_time, true);
        });

        $totalWeekHours = $weekHours + $weekProductionHours;

        return [
            Stat::make('Needs Attention', $pending + $unpaid)
                ->description(sprintf('%d pending, %d unpaid', $pending, $unpaid))
                ->color($pending + $unpaid > 0 ? 'warning' : 'success')
                ->icon('tabler-alert-circle'),

            Stat::make('This Week', number_format($totalWeekHours, 1) . ' hours')
                ->description(sprintf('$%s revenue • %d events', number_format($weekRevenue, 2), $weekProductions->count()))
                ->color('primary')
                ->icon('tabler-calendar-week'),

            Stat::make('Space Utilization', $this->getUtilizationRate())
                ->description('Based on 9am-10pm availability')
                ->color('info')
                ->icon('tabler-chart-bar'),
        ];
    }

    protected function getUtilizationRate(): string
    {
        $businessHoursPerDay = 13; // 9am-10pm
        $daysThisWeek = now()->diffInDays(now()->endOfWeek()) + 1;
        $totalAvailableHours = $businessHoursPerDay * $daysThisWeek;

        // Reservation hours
        $bookedHours = Reservation::whereBetween('reserved_at', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ])
        ->where('status', '!=', 'cancelled')
        ->sum('hours_used');

        // Production hours using space
        $productions = Production::whereBetween('start_time', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ])->get()->filter(fn($p) => $p->usesPracticeSpace());

        $productionHours = $productions->sum(function ($p) {
            return $p->start_time->diffInHours($p->end_time, true);
        });

        $totalBookedHours = $bookedHours + $productionHours;

        $rate = $totalAvailableHours > 0
            ? ($totalBookedHours / $totalAvailableHours) * 100
            : 0;

        return number_format($rate, 1) . '%';
    }
}
