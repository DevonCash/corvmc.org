<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;

class UserSummaryWidget extends Widget
{
    protected static ?int $sort = -3;

    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.user-summary-widget';

    public function getUserStats(): array
    {
        $user = Auth::user();

        if (!$user) {
            return [];
        }

        return Cache::remember("user_stats.{$user->id}", 300, function() use ($user) {
            $stats = [
                'upcoming_reservations' => $user->reservations()
                    ->where('reserved_at', '>', now())
                    ->count(),
                'band_memberships' => $user->bandProfiles()->count(),
                'owned_bands' => $user->ownedBands()->count(),
                'managed_productions' => $user->productions()->count(),
                'is_sustaining_member' => $user->isSustainingMember(),
            ];

            if ($user->isSustainingMember()) {
                $stats['remaining_free_hours'] = $user->getRemainingFreeHours();
                $stats['used_free_hours'] = $user->getUsedFreeHoursThisMonth();
            }

            // Profile completion check
            if ($user->profile) {
                $stats['profile_complete'] = $user->profile->isComplete();
            }

            return $stats;
        });
    }

    public function getRecentActivity(): array
    {
        $user = Auth::user();

        if (!$user) {
            return [];
        }

        return Cache::remember("user_activity.{$user->id}", 600, function() use ($user) {
            $activities = [];

            $recentReservations = $user->reservations()
                ->where('reserved_at', '>', now()->subDays(30))
                ->orderBy('reserved_at', 'desc')
                ->limit(3)
                ->get();

            foreach ($recentReservations as $reservation) {
                $activities[] = [
                    'type' => 'reservation',
                    'date' => $reservation->reserved_at,
                    'description' => 'Practice room booked for ' . $reservation->reserved_at->format('M j, g:i A'),
                    'icon' => 'tabler-calendar',
                    'url' => route('filament.member.resources.reservations.view', ['record' => $reservation->id]),
                    'model' => $reservation,
                ];
            }

            // Transaction functionality removed

            return collect($activities)
                ->sortByDesc('date')
                ->take(5)
                ->values()
                ->toArray();
        });
    }

    public function getStatsUrls(): array
    {
        return [
            'reservations' => route('filament.member.resources.reservations.index'),
            'bands' => route('filament.member.resources.bands.index'),
            'productions' => route('filament.member.resources.productions.index'),
        ];
    }
}
