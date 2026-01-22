<?php

namespace CorvMC\Membership\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ForceDeleteUser
{
    use AsAction;

    /**
     * Force delete a user permanently.
     */
    public function handle(User $user): bool
    {
        return DB::transaction(function () use ($user) {
            // Clean up related data
            $user->reservations()->forceDelete();
            $user->profile?->delete();

            return $user->forceDelete();
        });
    }
}
