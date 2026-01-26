<?php

namespace CorvMC\Membership\Actions\MemberProfiles;

use CorvMC\Membership\Models\MemberProfile;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateSkills
{
    use AsAction;

    /**
     * Update member profile skills.
     */
    public function handle(MemberProfile $profile, array $skills): bool
    {
        DB::transaction(function () use ($profile, $skills) {
            // Remove existing skills
            $profile->detachTags($profile->tagsWithType('skill'));

            // Add new skills
            foreach ($skills as $skill) {
                $profile->attachTag($skill, 'skill');
            }
        });

        return true;
    }
}
