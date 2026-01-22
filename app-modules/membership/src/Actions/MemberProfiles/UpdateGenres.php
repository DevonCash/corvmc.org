<?php

namespace CorvMC\Membership\Actions\MemberProfiles;

use App\Models\MemberProfile;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateGenres
{
    use AsAction;

    /**
     * Update member profile genres.
     */
    public function handle(MemberProfile $profile, array $genres): bool
    {
        $profile->syncTagsWithType($genres, 'genre');

        return true;
    }
}
