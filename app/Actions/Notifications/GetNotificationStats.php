<?php

namespace App\Actions\Notifications;

use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class GetNotificationStats
{
    use AsAction;

    /**
     * Get statistics about notification sending.
     */
    public function handle(): array
    {
        $tomorrow = Carbon::now()->addDay();

        return [
            'reservations_tomorrow' => Reservation::with('user')
                ->where('status', 'confirmed')
                ->whereBetween('reserved_at', [
                    $tomorrow->copy()->startOfDay(),
                    $tomorrow->copy()->endOfDay(),
                ])
                ->count(),
            'pending_reservations' => Reservation::where('status', 'pending')
                ->where('created_at', '<=', Carbon::now()->subDay())
                ->where('reserved_at', '>', Carbon::now())
                ->count(),
            'inactive_users' => User::whereNotNull('email_verified_at')
                ->whereDoesntHave('reservations', function ($query) {
                    $query->where('created_at', '>', Carbon::now()->subDays(90));
                })
                ->get()
                ->filter(function ($user) {
                    return ! $user->isSustainingMember();
                })
                ->count(),
        ];
    }
}
