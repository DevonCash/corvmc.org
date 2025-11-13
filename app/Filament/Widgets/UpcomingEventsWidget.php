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
                ->with(['performers', 'manager'])
                ->limit(8)
                ->get()
                ->map(function (Production $event) {
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
                        'ticket_url' => $event->ticket_url,
                        'ticket_price_display' => $event->ticket_price_display,
                        'is_free' => $event->isFree(),
                        'has_tickets' => $event->hasTickets(),
                        'is_notaflof' => $event->isNotaflof(),
                        'performers' => $event->performers->map(function ($band) {
                            return [
                                'id' => $band->id,
                                'name' => $band->name,
                                'order' => $band->pivot->order ?? 0,
                                'set_length' => $band->pivot->set_length,
                                'can_view' => $this->canViewBand($band),
                                'profile_url' => $this->canViewBand($band) ?
                                    route('filament.member.resources.bands.view', $band) : null,
                            ];
                        })->sortBy('order'),
                        'manager_name' => $event->manager?->name,
                        'genres' => $event->genres->pluck('name')->toArray(),
                        'edit_url' => Auth::user() &&
                            (Auth::user()->can('update productions') || $event->isManageredBy(Auth::user()))
                            ? route('filament.staff.resources.productions.edit', $event)
                            : null,
                        'public_url' => route('events.show', $event),
                    ];
                });
        });
    }

    protected function canViewBand($band): bool
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
