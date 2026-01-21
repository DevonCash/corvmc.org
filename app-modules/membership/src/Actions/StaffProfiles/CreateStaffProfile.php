<?php

namespace CorvMC\Membership\Actions\StaffProfiles;

use CorvMC\Membership\Models\StaffProfile;
use App\Models\StaffProfileType;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateStaffProfile
{
    use AsAction;

    /**
     * Create a new staff profile.
     */
    public function handle(array $data): StaffProfile
    {
        return DB::transaction(function () use ($data) {
            $data['type'] = $data['type'] ?? StaffProfileType::Staff;
            $staffProfile = StaffProfile::create($data);

            // Handle profile image upload if provided
            if (isset($data['profile_image'])) {
                $staffProfile->addMediaFromRequest('profile_image')
                    ->toMediaCollection('profile_image');
            }

            return $staffProfile;
        });
    }
}
