<?php

namespace App\Filament\Widgets;

use App\Models\Production;
use App\Models\Reservation;
use App\Models\User;
use Closure;
use Guava\Calendar\ValueObjects\CalendarEvent;
use Guava\Calendar\Widgets\CalendarWidget;
use Illuminate\Support\HtmlString;

class PracticeSpaceCalendar extends CalendarWidget
{
    protected Closure|HtmlString|string|null $heading = 'Weekly Practice Space Schedule';

    public function getOptions(): array
    {
        return [
            'nowIndicator' => true,

        ];
    }

    protected string $calendarView = 'resourceTimeGridWeek';

    public function getEvents(array $fetchInfo = []): array
    {
        // Get actual reservations
        @['start' => $start,'end' => $end] = $fetchInfo;

        $reservations = Reservation::withoutGlobalScopes()
            ->with('user')
            ->where('status', '!=', 'cancelled')
            ->where('reserved_until', '>=', $start)
            ->where('reserved_at', '<=', $end)
            ->get()
            ->map(function (Reservation $reservation) {
                $currentUser = User::me();
                $isOwnReservation = $currentUser?->id === $reservation->user_id;
                $canViewDetails = $currentUser?->can('view reservations');

                // Show full details for own reservations or if user has permission
                if ($isOwnReservation) {
                    $title = $reservation->user->name;
                    if ($reservation->notes) {
                        $title .= ' - '.$reservation->notes;
                    }
                } else {
                    $title = 'Reserved';
                }

                $color = match ($reservation->status) {
                    'confirmed' => '#10b981', // green
                    'pending' => '#f59e0b',   // yellow
                    'cancelled' => '#ef4444', // red
                    default => '#6b7280',     // gray
                };

                return CalendarEvent::make($reservation)
                    ->title($title)
                    ->start($reservation->reserved_at)
                    ->end($reservation->reserved_until)
                    ->backgroundColor($color)
                    ->textColor('#fff');
            });

        $productions = Production::with('manager')
            ->where('end_time', '>=', $start)
            ->where('start_time', '<=', $end)
            ->where('status', '!=', 'cancelled')
            ->get()
            ->filter(fn (Production $production) => $production->usesPracticeSpace())
            ->map(function (Production $production) {
                $title = $production->title;
                if (! $production->isPublished()) {
                    $title .= ' (Draft)';
                }

                $color = match ($production->status) {
                    'pre-production' => '#8b5cf6', // purple
                    'production' => '#3b82f6',     // blue
                    'completed' => '#10b981',      // green
                    'cancelled' => '#ef4444',      // red
                    default => '#6b7280',          // gray
                };

                return CalendarEvent::make($production)
                    ->title($title)
                    ->start($production->start_time)
                    ->end($production->end_time)
                    ->backgroundColor($color)
                    ->textColor('#fff');
            });

        // Return all events
        return collect([])->merge($reservations)->merge($productions)->toArray();
    }

    public function getConfig(): array
    {
        return [
            'headerToolbar' => [
                'left' => 'prev,next today',
                'center' => 'title',
                'right' => 'dayGridMonth,timeGridWeek,timeGridDay',
            ],
            'initialView' => 'timeGridWeek',
            'initialDate' => now()->format('Y-m-d'),
            'firstDay' => 1, // Start week on Monday
            'slotMinTime' => '09:00:00',
            'slotMaxTime' => '22:00:00',
            'businessHours' => [
                'daysOfWeek' => [1, 2, 3, 4, 5, 6, 0], // Monday through Sunday
                'startTime' => '09:00',
                'endTime' => '22:00',
            ],
            'height' => 'auto',
            'eventDisplay' => 'block',
            'displayEventTime' => true,
            'eventTimeFormat' => [
                'hour' => 'numeric',
                'minute' => '2-digit',
                'omitZeroMinute' => false,
            ],
            'eventDidMount' => 'function(info) { console.log("Event mounted:", info.event.title, info.event.start); }',
            'eventSourceSuccess' => 'function(events, response) { console.log("Events loaded:", events.length, "events"); }',
        ];
    }
}
