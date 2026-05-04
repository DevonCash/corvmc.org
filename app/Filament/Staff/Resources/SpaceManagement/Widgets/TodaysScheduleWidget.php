<?php

namespace App\Filament\Staff\Resources\SpaceManagement\Widgets;

use App\Models\EventReservation;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\Models\Reservation;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;

class TodaysScheduleWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $today = Reservation::with('reservable')
            ->whereDate('reserved_at', today())
            ->where('status', '!=', 'cancelled')
            ->orderBy('reserved_at', 'asc')
            ->get();

        $rehearsals = $today->filter(fn ($r) => $r instanceof RehearsalReservation);
        $events = $today->filter(fn ($r) => $r instanceof EventReservation);
        $totalHours = $today->sum('duration');

        // Next upcoming reservation (today or future)
        $next = $today->first(fn ($r) => $r->reserved_at->isFuture());

        $nextDescription = $next
            ? $next->reserved_at->format('g:i A') . ' — ' . ($next->getResponsibleUser()?->name ?? $next->getDisplayTitle())
            : ($today->isNotEmpty() ? 'All started or completed' : 'Nothing scheduled');

        return [
            Stat::make('Today', new HtmlString($today->count() . ' <span class="text-sm font-normal text-gray-500 dark:text-gray-400">reservations</span>'))
                ->description(sprintf('%d rehearsals • %d events • %s hrs', $rehearsals->count(), $events->count(), number_format($totalHours, 1)))
                ->color('primary')
                ->icon('tabler-calendar-event'),

            Stat::make('Up Next', $next ? $next->reserved_at->format('g:i A') : '—')
                ->description($next ? ($next->getResponsibleUser()?->name ?? $next->getDisplayTitle()) : $nextDescription)
                ->icon('tabler-clock'),
        ];
    }
}
