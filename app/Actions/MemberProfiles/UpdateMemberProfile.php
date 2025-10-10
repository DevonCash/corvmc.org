<?php

namespace App\Actions\MemberProfiles;

use App\Models\MemberProfile;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateMemberProfile
{
    use AsAction;

    /**
     * Update a member profile.
     */
    public function handle(MemberProfile $profile, array $data): MemberProfile
    {
        return DB::transaction(function () use ($profile, $data) {
            $profile->update($data);

            // Handle tag updates
            if (isset($data['skills'])) {
                UpdateSkills::run($profile, $data['skills']);
            }

            if (isset($data['genres'])) {
                UpdateGenres::run($profile, $data['genres']);
            }

            if (isset($data['influences'])) {
                UpdateInfluences::run($profile, $data['influences']);
            }

            // Handle media updates
            if (isset($data['avatar'])) {
                $profile->clearMediaCollection('avatar');
                $profile->addMediaFromRequest('avatar')
                    ->toMediaCollection('avatar');
            }

            return $profile->fresh();
        });
    }
}
