<?php

namespace App\Filament\Widgets;

use App\Models\Event;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Closure;
use Guava\Calendar\ValueObjects\CalendarEvent;
use Guava\Calendar\Widgets\CalendarWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class MonthlyCalendarWidget extends CalendarWidget
{
    protected Closure|HtmlString|string|null $heading = 'Practice Space Overview';

    protected int|string|array $columnSpan = 'full';

    public function getEvents(array $fetchInfo = []): array
    {
        // Get actual reservations
        // $start = Carbon::parse($fetchInfo['start'] ?? now()->startOfMonth()->subMonth());
        // $end = Carbon::parse($fetchInfo['end'] ?? now()->endOfMonth()->addMonth());

        $reservations = Reservation::withoutGlobalScopes()
            ->with('user')
            ->where('status', '!=', 'cancelled')
            ->get()
            ->map(function (Reservation $reservation) {
                $currentUser = Auth::user();
                $isOwnReservation = $currentUser?->id === $reservation->user_id;
                $canViewDetails = $currentUser?->can('view reservations');

                // Show full details for own reservations or if user has permission
                if ($isOwnReservation || $canViewDetails) {
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
                    ->action('view')
                    ->textColor('#fff');
            });

        $events = Event::with('organizer')
            ->get()
            ->filter(fn (Event $event) => $event->usesPracticeSpace())
            ->map(function (Event $event) {
                $title = $event->title;
                if (! $event->isPublished()) {
                    $title .= ' (Draft)';
                }

                $color = match ($event->status) {
                    'pre-production' => '#8b5cf6', // purple
                    'production' => '#3b82f6',     // blue
                    'completed' => '#10b981',      // green
                    'cancelled' => '#ef4444',      // red
                    default => '#6b7280',          // gray
                };

                return CalendarEvent::make($event)
                    ->title($title)
                    ->start($event->start_time)
                    ->end($event->end_time)
                    ->backgroundColor($color)
                    ->action('view')
                    ->textColor('#fff');
            });

        // Return all events
        return $reservations
            ->merge($events)
            ->map(fn ($event) => $event->action('view'))
            ->toArray();
    }

    public function getConfig(): array
    {
        return [
            'headerToolbar' => [
                'left' => 'prev,next today',
                'center' => 'title',
                'right' => 'dayGridMonth',
            ],
            'initialView' => 'dayGridMonth',
            'initialDate' => now()->format('Y-m-d'),
            'firstDay' => 1, // Start week on Monday
            'height' => 600,
            'eventDisplay' => 'block',
            'displayEventTime' => false, // Don't show time in month view
            'dayMaxEvents' => 3, // Show max 3 events per day, then "+X more"
            'moreLinkClick' => 'popover',
            'eventDidMount' => 'function(info) { console.log("Event mounted:", info.event.title, info.event.start); }',
        ];
    }
}
