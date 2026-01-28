<?php

namespace CorvMC\Membership\Actions\StaffProfiles;

use App\Models\StaffProfile;
use Lorisleiva\Actions\Concerns\AsAction;

class UnlinkFromUser
{
    use AsAction;

    /**
     * Unlink staff profile from user account.
     */
    public function handle(StaffProfile $staffProfile): bool
    {
        return $staffProfile->update(['user_id' => null]);
    }
}
