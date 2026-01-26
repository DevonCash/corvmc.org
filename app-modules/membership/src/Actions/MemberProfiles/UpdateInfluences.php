<?php

namespace CorvMC\Membership\Actions\MemberProfiles;

use CorvMC\Membership\Models\MemberProfile;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateInfluences
{
    use AsAction;

    /**
     * Update member profile influences.
     */
    public function handle(MemberProfile $profile, array $influences): bool
    {
        DB::transaction(function () use ($profile, $influences) {
            // Remove existing influences
            $profile->detachTags($profile->tagsWithType('influence'));

            // Add new influences
            foreach ($influences as $influence) {
                $profile->attachTag($influence, 'influence');
            }
        });

        return true;
    }
}
