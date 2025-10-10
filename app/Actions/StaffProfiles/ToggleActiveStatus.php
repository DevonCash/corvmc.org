<?php

namespace App\Actions\StaffProfiles;

use App\Models\StaffProfile;
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
            'is_active' => !$staffProfile->is_active
        ]);
    }
}
