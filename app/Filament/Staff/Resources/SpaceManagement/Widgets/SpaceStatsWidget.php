<?php

namespace App\Filament\Staff\Resources\SpaceManagement\Widgets;

use App\Models\EventReservation;
use CorvMC\Events\Models\Event;
use CorvMC\Finance\Models\Order;
use CorvMC\Finance\States\OrderState;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\Models\Reservation;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;

class SpaceStatsWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        return [
            $this->todayStat(),
            $this->weekStat(),
            $this->revenueStat(),
        ];
    }

    private function todayStat(): Stat
    {
        $today = Reservation::with('reservable')
            ->whereDate('reserved_at', today())
            ->where('status', '!=', 'cancelled')
            ->orderBy('reserved_at', 'asc')
            ->get();

        $rehearsals = $today->filter(fn ($r) => $r instanceof RehearsalReservation);
        $events = $today->filter(fn ($r) => $r instanceof EventReservation);
        $totalHours = $today->sum('duration');

        return Stat::make('Today', new HtmlString($today->count() . ' <span class="text-sm font-normal text-gray-500 dark:text-gray-400">reservations</span>'))
            ->description(sprintf('%d rehearsals • %d events • %s hrs', $rehearsals->count(), $events->count(), number_format($totalHours, 1)))
            ->color('primary')
            ->icon('tabler-calendar-event');
    }

    private function upNextStat(): Stat
    {
        $next = Reservation::with('reservable')
            ->whereDate('reserved_at', today())
            ->where('reserved_at', '>', now())
            ->where('status', '!=', 'cancelled')
            ->orderBy('reserved_at', 'asc')
            ->first();

        $description = $next
            ? ($next->getResponsibleUser()?->name ?? $next->getDisplayTitle())
            : 'Nothing upcoming today';

        return Stat::make('Up Next', $next ? $next->reserved_at->format('g:i A') : '—')
            ->description($description)
            ->icon('tabler-clock');
    }

    private function weekStat(): Stat
    {
        $weekReservations = Reservation::whereBetween('reserved_at', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ])
            ->where('status', '!=', 'cancelled')
            ->get();

        $weekHours = $weekReservations->sum('hours_used');
        $weekReservationCount = $weekReservations->count();

        $weekEvents = Event::whereBetween('start_datetime', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ])->get()->filter(fn ($e) => $e->usesPracticeSpace());

        $weekEventHours = $weekEvents->sum(function ($e) {
            return $e->start_datetime->diffInHours($e->end_datetime, true);
        });

        $totalWeekHours = $weekHours + $weekEventHours;

        return Stat::make('This Week', number_format($totalWeekHours, 1) . ' hours')
            ->description(sprintf('%d reservations • %d events', $weekReservationCount, $weekEvents->count()))
            ->color('primary')
            ->icon('tabler-calendar-week');
    }

    private function revenueStat(): Stat
    {
        $monthRevenue = $this->settledRevenueForPeriod(now()->startOfMonth(), now()->endOfMonth());
        $weekRevenue = $this->settledRevenueForPeriod(now()->startOfWeek(), now()->endOfWeek());
        $pending = $this->pendingRevenueForPeriod(now()->startOfMonth(), now()->endOfMonth());

        $parts = ['$' . number_format($weekRevenue / 100, 2) . ' this week'];
        if ($pending > 0) {
            $parts[] = '$' . number_format($pending / 100, 2) . ' pending';
        }

        return Stat::make('Revenue', new HtmlString('$' . number_format($monthRevenue / 100, 2) . ' <span class="text-sm font-normal text-gray-500 dark:text-gray-400">this month</span>'))
            ->description(implode(' • ', $parts))
            ->color('primary')
            ->icon('tabler-cash');
    }

    private function settledRevenueForPeriod($start, $end): int
    {
        return Order::query()
            ->whereIn('status', [OrderState\Completed::$name, OrderState\Comped::$name])
            ->whereBetween('settled_at', [$start, $end])
            ->whereHas('lineItems', fn ($q) => $q->where('product_type', 'rehearsal_time'))
            ->sum('total_amount');
    }

    private function pendingRevenueForPeriod($start, $end): int
    {
        return Order::query()
            ->where('status', OrderState\Pending::$name)
            ->whereBetween('created_at', [$start, $end])
            ->whereHas('lineItems', fn ($q) => $q->where('product_type', 'rehearsal_time'))
            ->sum('total_amount');
    }
}
