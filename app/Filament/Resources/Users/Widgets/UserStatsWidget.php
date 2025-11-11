<?php

namespace App\Filament\Resources\Users\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UserStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalMembers = User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['member', 'sustaining member', 'staff', 'admin']);
        })->count();

        $sustainingMembers = User::role('sustaining member')->count();

        $regularMembers = User::role('member')
            ->whereDoesntHave('roles', function ($query) {
                $query->whereIn('name', ['sustaining member', 'staff', 'admin']);
            })
            ->count();

        $staffAndAdmins = User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['staff', 'admin']);
        })->count();

        return [
            Stat::make('Total Members', $totalMembers)
                ->description('All active users')
                ->descriptionIcon('tabler-users')
                ->color('primary'),

            Stat::make('Sustaining Members', $sustainingMembers)
                ->description('Monthly supporters')
                ->descriptionIcon('tabler-user-heart')
                ->color('success'),

            Stat::make('Regular Members', $regularMembers)
                ->description('Standard membership')
                ->descriptionIcon('tabler-user')
                ->color('info'),

            Stat::make('Staff & Admins', $staffAndAdmins)
                ->description('Management team')
                ->descriptionIcon('tabler-user-cog')
                ->color('warning'),
        ];
    }

    public function getDisplayName(): string
    {
        return 'Membership Overview';
    }
}
