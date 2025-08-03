<?php

namespace App\Filament\Resources\Reservations\Widgets;

use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ReservationStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $user = User::me();
        $today = Carbon::today();
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        // Today's reservations
        $todayReservations = Reservation::where('user_id', $user->id)
            ->whereDate('reserved_at', $today)
            ->where('status', '!=', 'cancelled')
            ->count();

        $todayHours = Reservation::where('user_id', $user->id)
            ->whereDate('reserved_at', $today)
            ->where('status', '!=', 'cancelled')
            ->sum('hours_used');

        // This week's reservations
        $weekReservations = Reservation::where('user_id', $user->id)
            ->whereBetween('reserved_at', [$startOfWeek, $endOfWeek])
            ->where('status', '!=', 'cancelled')
            ->count();

        $weekHours = Reservation::where('user_id', $user->id)
            ->whereBetween('reserved_at', [$startOfWeek, $endOfWeek])
            ->where('status', '!=', 'cancelled')
            ->sum('hours_used');

        // This month's reservations
        $monthReservations = Reservation::where('user_id', $user->id)
            ->whereBetween('reserved_at', [$startOfMonth, $endOfMonth])
            ->where('status', '!=', 'cancelled')
            ->count();

        $monthHours = Reservation::where('user_id', $user->id)
            ->whereBetween('reserved_at', [$startOfMonth, $endOfMonth])
            ->where('status', '!=', 'cancelled')
            ->sum('hours_used');

        // Free hours information
        $remainingFreeHours = $user->getRemainingFreeHours();
        $usedFreeHours = $user->getUsedFreeHoursThisMonth();
        $totalFreeHours = $user->isSustainingMember() ? 4 : 0;

        return [
            Stat::make('Today', $todayReservations)
                ->description($todayHours > 0 ? number_format($todayHours, 1).' hours booked' : 'No practice time today')
                ->descriptionIcon($todayReservations > 0 ? 'heroicon-m-clock' : 'heroicon-m-calendar')
                ->color($todayReservations > 0 ? 'success' : 'gray')
                ->chart($this->getTodayChart()),

            Stat::make('This Week', $weekReservations)
                ->description($weekHours > 0 ? number_format($weekHours, 1).' hours total' : 'No bookings this week')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color($weekReservations > 0 ? 'info' : 'gray'),

            Stat::make('This Month', $monthReservations)
                ->description($monthHours > 0 ? number_format($monthHours, 1).' hours total' : 'No bookings this month')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color($monthReservations > 0 ? 'primary' : 'gray'),

            Stat::make('Free Hours', $remainingFreeHours.'/'.$totalFreeHours)
                ->description($user->isSustainingMember()
                    ? ($usedFreeHours > 0 ? 'Used '.number_format($usedFreeHours, 1).' this month' : 'None used this month')
                    : 'Upgrade to sustaining member'
                )
                ->descriptionIcon($user->isSustainingMember() ? 'heroicon-m-gift' : 'heroicon-m-arrow-up-circle')
                ->color($user->isSustainingMember()
                    ? ($remainingFreeHours > 0 ? 'success' : 'warning')
                    : 'gray'
                ),
        ];
    }

    protected function getTodayChart(): array
    {
        // Simple chart showing hourly activity for today
        $hourlyData = [];
        $user = User::me();

        for ($hour = 9; $hour <= 21; $hour++) {
            $hasReservation = Reservation::where('user_id', $user->id)
                ->whereDate('reserved_at', Carbon::today())
                ->whereTime('reserved_at', '<=', sprintf('%02d:59:59', $hour))
                ->whereTime('reserved_until', '>', sprintf('%02d:00:00', $hour))
                ->where('status', '!=', 'cancelled')
                ->exists();

            $hourlyData[] = $hasReservation ? 1 : 0;
        }

        return $hourlyData;
    }

    public function getDisplayName(): string
    {
        return 'Your Reservation Summary';
    }
}
