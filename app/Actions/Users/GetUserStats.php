<?php

namespace App\Actions\Users;

use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class GetUserStats
{
    use AsAction;

    /**
     * Get user statistics.
     */
    public function handle(): array
    {
        return [
            'total_users' => User::count(),
            'active_users' => User::whereNull('deleted_at')->count(),
            'deactivated_users' => User::onlyTrashed()->count(),
            'users_this_month' => User::where('created_at', '>=', now()->startOfMonth())->count(),
            'sustaining_members' => User::whereHas('roles', function ($q) {
                $q->where('name', 'sustaining member');
            })->count(),
        ];
    }
}
