<?php

namespace App\Actions\MemberProfiles;

use App\Models\MemberProfile;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteMemberProfile
{
    use AsAction;

    /**
     * Delete a member profile.
     */
    public function handle(MemberProfile $profile): bool
    {
        return DB::transaction(function () use ($profile) {
            // Clear all media
            $profile->clearMediaCollection('avatar');

            // Detach all tags
            $profile->detachTags($profile->tags);

            return $profile->delete();
        });
    }
}
