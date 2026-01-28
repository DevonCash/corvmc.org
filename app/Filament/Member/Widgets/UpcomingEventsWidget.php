<?php

namespace App\Filament\Member\Widgets;

use CorvMC\Bands\Models\Band;
use CorvMC\Events\Models\Event;
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
                ->with(['performers', 'organizer', 'venue'])
                ->limit(8)
                ->get();
        });
    }

    protected function canViewBand(Band $band): bool
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
