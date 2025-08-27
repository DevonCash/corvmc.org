<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class QuickActionsWidget extends Widget
{
    protected static ?int $sort = -4;

    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.widgets.quick-actions-widget';

    public function getQuickActions(): array
    {
        $user = auth()->user();

        if (!$user) {
            return [];
        }

        $actions = [];

        // Always available actions
        $actions[] = [
            'label' => 'Book Practice Space',
            'description' => 'Reserve a room for rehearsal',
            'icon' => 'tabler-calendar-plus',
            'color' => 'primary',
            'url' => route('filament.member.resources.reservations.index'),
        ];

        // Band-related actions
        if ($user->bandProfiles()->count() === 0) {
            $actions[] = [
                'label' => 'Create New Band',
                'description' => 'Start or join a musical group',
                'icon' => 'tabler-users-plus',
                'color' => 'success',
                'url' => route('filament.member.resources.bands.create'),
            ];
        } else {
            $actions[] = [
                'label' => 'Manage My Bands',
                'description' => 'Edit band profiles and members',
                'icon' => 'tabler-users',
                'color' => 'info',
                'url' => route('filament.member.resources.bands.index'),
            ];
        }


        // Community actions
        $actions[] = [
            'label' => 'Browse Members',
            'description' => 'Connect with other musicians',
            'icon' => 'tabler-users-group',
            'color' => 'gray',
            'url' => route('filament.member.resources.directory.index'),
        ];

        return collect($actions)->take(6)->toArray();
    }

    public function getNextAvailableSlot(): ?array
    {
        // Get next available practice room slot (simplified)
        $nextSlot = \App\Models\Reservation::where('reserved_at', '>', now())
            ->orderBy('reserved_at')
            ->first();

        if (!$nextSlot) {
            // Find next business hour if no existing reservations
            $tomorrow = now()->addDay()->setHour(10)->setMinute(0)->setSecond(0);
            return [
                'time' => $tomorrow,
                'available' => true,
            ];
        }

        return [
            'time' => $nextSlot->reserved_at->addHours(2), // Assume 2-hour slots
            'available' => true,
        ];
    }
}
