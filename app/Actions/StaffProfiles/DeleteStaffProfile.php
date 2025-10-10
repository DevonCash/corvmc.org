<?php

namespace App\Actions\StaffProfiles;

use App\Models\StaffProfile;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteStaffProfile
{
    use AsAction;

    /**
     * Delete a staff profile.
     */
    public function handle(StaffProfile $staffProfile): bool
    {
        return DB::transaction(function () use ($staffProfile) {
            // Clear all media
            $staffProfile->clearMediaCollection('profile_image');

            return $staffProfile->delete();
        });
    }
}
