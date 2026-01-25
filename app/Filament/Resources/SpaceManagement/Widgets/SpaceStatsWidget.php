<?php

namespace App\Filament\Resources\SpaceManagement\Widgets;

use CorvMC\SpaceManagement\Enums\ReservationStatus;
use CorvMC\Events\Models\Event;
use CorvMC\SpaceManagement\Models\Reservation;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SpaceStatsWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $needsAttention = Reservation::needsAttention()->count();

        // This week's reservations
        $weekReservations = Reservation::whereBetween('reserved_at', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ])
            ->where('status', '!=', ReservationStatus::Cancelled->value)
            ->get();

        $weekHours = $weekReservations->sum('hours_used');
        $weekRevenue = $weekReservations
            ->filter(fn ($r) => $r instanceof \CorvMC\SpaceManagement\Models\RehearsalReservation && $r->payment_status === 'paid')
            ->sum(fn ($r) => $r->cost->getMinorAmount()->toInt()) / 100;
        $weekReservationCount = $weekReservations->count();

        // This week's events using space
        $weekEvents = Event::whereBetween('start_datetime', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ])->get()->filter(fn ($e) => $e->usesPracticeSpace());

        $weekEventHours = $weekEvents->sum(function ($e) {
            return $e->start_datetime->diffInHours($e->end_datetime, true);
        });

        $totalWeekHours = $weekHours + $weekEventHours;

        return [
            Stat::make('Needs Attention', $needsAttention)
                ->description('Pending auto-cancels & past unpaid')
                ->color($needsAttention > 0 ? 'warning' : 'success')
                ->icon('tabler-alert-circle'),

            Stat::make('This Week', number_format($totalWeekHours, 1).' hours')
                ->description(sprintf('$%s revenue • %d reservations • %d events', number_format($weekRevenue, 2), $weekReservationCount, $weekEvents->count()))
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
        $daysThisWeek = now()->startOfWeek()->diffInDays(now()->endOfWeek()) + 1;
        $totalAvailableHours = $businessHoursPerDay * $daysThisWeek;

        // Reservation hours
        $bookedHours = Reservation::whereBetween('reserved_at', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ])
            ->where('status', '!=', ReservationStatus::Cancelled->value)
            ->sum('hours_used');

        // Event hours using space
        $events = Event::whereBetween('start_datetime', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ])->get()->filter(fn ($e) => $e->usesPracticeSpace());

        $eventHours = $events->sum(function ($e) {
            return $e->start_datetime->diffInHours($e->end_datetime, true);
        });

        $totalBookedHours = $bookedHours + $eventHours;

        $rate = $totalAvailableHours > 0
            ? ($totalBookedHours / $totalAvailableHours) * 100
            : 0;

        return number_format($rate, 1).'%';
    }
}
