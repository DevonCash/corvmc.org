<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\Widget;

class QuickActionsWidget extends Widget
{
    protected static ?int $sort = -4;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.quick-actions-widget';

    public function getQuickActions(): array
    {
        $user = User::me();

        if (! $user) {
            return [];
        }

        $actions = [
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

        return $actions;
    }

    public function getUserStats(): array
    {
        $user = User::me();

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

            // Profile completion check
            if ($user->profile) {
                $stats['profile_complete'] = $user->profile->isComplete();
            }

            return $stats;
        });
    }
}
