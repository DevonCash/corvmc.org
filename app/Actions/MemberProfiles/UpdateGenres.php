<?php

namespace App\Actions\MemberProfiles;

use App\Models\MemberProfile;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateGenres
{
    use AsAction;

    /**
     * Update member profile genres.
     */
    public function handle(MemberProfile $profile, array $genres): bool
    {
        DB::transaction(function () use ($profile, $genres) {
            // Remove existing genres
            $profile->detachTags($profile->tagsWithType('genre'));

            // Add new genres
            foreach ($genres as $genre) {
                $profile->attachTag($genre, 'genre');
            }
        });

        return true;
    }
}
