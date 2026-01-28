<?php

namespace CorvMC\Membership\Actions\StaffProfiles;

use App\Models\StaffProfile;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class LinkToUser
{
    use AsAction;

    /**
     * Link staff profile to user account.
     */
    public function handle(StaffProfile $staffProfile, User $user): bool
    {
        return $staffProfile->update(['user_id' => $user->id]);
    }
}
