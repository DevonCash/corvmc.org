<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;
use Filament\Panel;

class MemberDashboard extends Page
{
    use HasFiltersForm;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-home';

    protected string $view = 'filament.pages.member-dashboard';

    protected static ?string $title = 'Dashboard';

    protected static ?string $navigationLabel = 'Home';

    protected static ?int $navigationSort = -100;

    public static function getRoutePath(Panel $panel): string
    {
        return '/';
    }

    public function getWidgets(): array
    {
        return [];
    }

    public function getUpcomingReservations()
    {
        $user = \App\Models\User::me();

        if (! $user) {
            return collect();
        }

        return $user->rehearsals()
            ->where('reserved_at', '>', now())
            ->orderBy('reserved_at')
            ->limit(5)
            ->get();
    }

    public function getQuickActions(): array
    {
        $user = \App\Models\User::me();

        if (! $user) {
            return [];
        }

        return [
            [
                'label' => 'Practice Space',
                'description' => 'Reserve a room for rehearsal',
                'icon' => 'tabler-calendar-plus',
                'color' => 'primary',
                'url' => route('filament.member.resources.reservations.index'),
            ],
            [
                'label' => 'My Bands',
                'description' => $user->bands->count() === 0 ? 'Start or join a musical group' : 'Edit band profiles and members',
                'icon' => 'tabler-users',
                'color' => 'info',
                'url' => route('filament.member.resources.bands.index'),
            ],
            [
                'label' => 'Members',
                'description' => 'Connect with other musicians',
                'icon' => 'tabler-users-group',
                'color' => 'gray',
                'url' => route('filament.member.resources.directory.index'),
            ],
            [
                'label' => 'My Membership',
                'description' => $user->isSustainingMember() ? 'Manage your membership' : 'Become a sustaining member',
                'icon' => 'tabler-star',
                'color' => 'warning',
                'url' => \App\Filament\Pages\MyMembership::getUrl(),
            ],
        ];
    }

    public function getUpcomingEvents()
    {
        $userId = auth()->id();
        $cacheKey = 'upcoming_events'.($userId ? ".user_{$userId}" : '');

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 600, function () {
            return \App\Models\Event::publishedUpcoming()
                ->with(['performers', 'organizer'])
                ->limit(8)
                ->get()
                ->map(function (\App\Models\Event $event) {
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
                        'performers' => $event->performers->map(function ($band) {
                            /** @var \App\Models\Band $band */
                            return [
                                'id' => $band->getKey(),
                                'name' => $band->name,
                                'order' => $band->pivot->order ?? 0,
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

    protected function canViewBand($band): bool
    {
        $currentUser = auth()->user();

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

    public function getUserStats(): array
    {
        $user = \App\Models\User::me();

        if (! $user) {
            return [];
        }

        return \Illuminate\Support\Facades\Cache::remember("user_stats.{$user->id}", 300, function () use ($user) {
            $stats = [
                'upcoming_reservations' => $user->rehearsals
                    ->where('reserved_at', '>', now())
                    ->count(),
                'band_memberships' => $user->bands->count(),
                'owned_bands' => $user->ownedBands->count(),
                'is_sustaining_member' => $user->isSustainingMember(),
            ];

            if ($user->isSustainingMember()) {
                $stats['remaining_free_hours'] = $user->getRemainingFreeHours();
                $stats['used_free_hours'] = $user->getUsedFreeHoursThisMonth();
            }

            if ($user->profile) {
                $stats['profile_complete'] = $user->profile->isComplete();
            }

            return $stats;
        });
    }

    public function getColumns(): int|string|array
    {
        return 1;
    }

    public function getVisibleWidgets(): array
    {
        return $this->filterVisibleWidgets($this->getWidgets());
    }

    protected function filterVisibleWidgets(array $widgets): array
    {
        return array_filter($widgets, function (string $widget): bool {
            return $widget::canView();
        });
    }

    public function isLazyWidget(string $widget): bool
    {
        return $widget::$isLazy ?? true;
    }
}
