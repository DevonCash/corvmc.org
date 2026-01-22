<?php

namespace CorvMC\Membership\Actions\StaffProfiles;

use App\Models\StaffProfile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateStaffProfile
{
    use AsAction;

    /**
     * Update a staff profile.
     */
    public function handle(StaffProfile $staffProfile, array $data): StaffProfile
    {
        $user = Auth::user();
        foreach ($data as $key => $value) {
            // TODO: Add proper policy check
            // if (!$user?->can('updateField', [$staffProfile, $key])) {
            //     throw new \Exception("cannot modify restricted fields");
            // }
        }

        return DB::transaction(function () use ($staffProfile, $data, $user) {
            // Admin users can make direct updates, bypassing the revision system
            if ($user?->hasPermissionTo('manage staff profiles')) {
                $staffProfile->forceUpdate($data);
            } else {
                $staffProfile->update($data);
            }

            // Handle profile image upload if provided
            if (isset($data['profile_image'])) {
                $staffProfile->clearMediaCollection('profile_image');
                $staffProfile->addMediaFromRequest('profile_image')
                    ->toMediaCollection('profile_image');
            }

            return $staffProfile->fresh();
        });
    }
}
