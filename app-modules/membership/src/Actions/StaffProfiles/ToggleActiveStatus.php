<?php

namespace CorvMC\Membership\Actions\StaffProfiles;

use CorvMC\Membership\Models\StaffProfile;
use Lorisleiva\Actions\Concerns\AsAction;

class ToggleActiveStatus
{
    use AsAction;

    /**
     * Toggle active status.
     */
    public function handle(StaffProfile $staffProfile): bool
    {
        return $staffProfile->update([
            'is_active' => ! $staffProfile->is_active,
        ]);
    }
}
