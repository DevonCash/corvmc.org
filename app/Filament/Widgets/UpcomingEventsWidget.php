<?php

namespace App\Filament\Widgets;

use App\Models\Event;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class UpcomingEventsWidget extends Widget
{
    protected string $view = 'filament.widgets.upcoming-events-widget';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public function getUpcomingEvents()
    {
        $userId = Auth::id();
        $cacheKey = 'upcoming_events'.($userId ? ".user_{$userId}" : '');

        return Cache::remember($cacheKey, 600, function () {
            return Event::publishedUpcoming()
                ->with(['performers', 'organizer'])
                ->limit(8)
                ->get()
                ->map(function (Event $event) {
                    return [
                        'id' => $event->id,
                        'title' => $event->title,
                        'description' => $event->description,
                        'start_time' => $event->start_time,
                        'end_time' => $event->end_time,
                        'doors_time' => $event->doors_time,
                        'date_range' => $event->date_range,
                        'venue_name' => $event->venue_name,
                        'venue_details' => $event->venue_details,
                        'poster_url' => $event->poster_url,
                        'poster_thumb_url' => $event->poster_thumb_url,
                        'ticket_url' => $event->event_link ?? $event->ticket_url,
                        'ticket_price_display' => $event->ticket_price_display,
                        'is_free' => $event->isFree(),
                        'has_tickets' => $event->hasTickets(),
                        'is_notaflof' => $event->isNotaflof(),
                        'performers' => $event->performers->map(function (\App\Models\Band $band) {
                            return [
                                'id' => $band->id,
                                'name' => $band->name,
                                /** @phpstan-ignore property.notFound */
                                'order' => $band->pivot->order ?? 0,
                                /** @phpstan-ignore property.notFound */
                                'set_length' => $band->pivot->set_length,
                                'can_view' => $this->canViewBand($band),
                                'profile_url' => $this->canViewBand($band) ?
                                    route('filament.member.resources.bands.view', $band) : null,
                            ];
                        })->sortBy('order'),
                        'organizer_name' => $event->organizer?->name,
                        'genres' => $event->genres->pluck('name')->toArray(),
                        'public_url' => route('events.show', $event),
                    ];
                });
        });
    }

    protected function canViewBand(\App\Models\Band $band): bool
    {
        $currentUser = Auth::user();

        if ($band->visibility === 'public') {
            return true;
        }

        if (! $currentUser) {
            return false;
        }

        if ($band->visibility === 'members') {
            return true;
        }

        if ($band->visibility === 'private') {
            return $band->owner_id === $currentUser->id ||
                   $band->members()->wherePivot('user_id', $currentUser->id)->exists();
        }

        return false;
    }

    public static function canView(): bool
    {
        return true; // Everyone can see published events
    }
}
