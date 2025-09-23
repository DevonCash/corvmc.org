<?php

namespace App\Filament\Widgets;

use App\Models\Production;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;

class UpcomingEventsWidget extends Widget
{
    protected string $view = 'filament.widgets.upcoming-events-widget';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public function getUpcomingEvents()
    {
        $userId = auth()->id();
        $cacheKey = "upcoming_events" . ($userId ? ".user_{$userId}" : '');
        
        return Cache::remember($cacheKey, 600, function() {
            return Production::publishedUpcoming()
                ->with(['performers', 'manager'])
                ->limit(8)
                ->get()
                ->map(function (Production $production) {
                    return [
                        'id' => $production->id,
                        'title' => $production->title,
                        'description' => $production->description,
                        'start_time' => $production->start_time,
                        'end_time' => $production->end_time,
                        'doors_time' => $production->doors_time,
                        'date_range' => $production->date_range,
                        'venue_name' => $production->venue_name,
                        'venue_details' => $production->venue_details,
                        'poster_url' => $production->poster_url,
                        'poster_thumb_url' => $production->poster_thumb_url,
                        'ticket_url' => $production->ticket_url,
                        'ticket_price_display' => $production->ticket_price_display,
                        'is_free' => $production->isFree(),
                        'has_tickets' => $production->hasTickets(),
                        'is_notaflof' => $production->isNotaflof(),
                        'performers' => $production->performers->map(function ($band) {
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
                        'manager_name' => $production->manager?->name,
                        'genres' => $production->genres->pluck('name')->toArray(),
                        'edit_url' => Auth::user() && 
                            (Auth::user()->can('update productions') || $production->isManageredBy(Auth::user())) 
                            ? route('filament.member.resources.productions.edit', $production) 
                            : null,
                        'public_url' => route('events.show', $production),
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
        
        if (!$currentUser) {
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