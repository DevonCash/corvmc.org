<?php

namespace CorvMC\Membership\Actions\MemberProfiles;

use CorvMC\Membership\Events\MemberProfileDeleted as MemberProfileDeletedEvent;
use CorvMC\Membership\Models\MemberProfile;
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
        $result = DB::transaction(function () use ($profile) {
            // Clear all media
            $profile->clearMediaCollection('avatar');

            // Detach all tags
            $profile->detachTags($profile->tags);

            return $profile->delete();
        });

        if ($result) {
            MemberProfileDeletedEvent::dispatch($profile);
        }

        return $result;
    }
}
