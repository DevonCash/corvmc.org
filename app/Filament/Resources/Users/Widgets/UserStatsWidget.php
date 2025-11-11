<?php

namespace App\Filament\Resources\Users\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UserStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalUsers = User::count();

        $sustainingMembers = User::role('sustaining member')->count();

        return [
            Stat::make('Total Members', $totalUsers)
                ->description('All Members')
                ->descriptionIcon('tabler-users')
                ->color('primary'),

            Stat::make('Sustaining Members', $sustainingMembers)
                ->description('Monthly supporters')
                ->descriptionIcon('tabler-user-heart')
                ->color('success'),
        ];
    }

    public function getDisplayName(): string
    {
        return 'Membership Overview';
    }
}
