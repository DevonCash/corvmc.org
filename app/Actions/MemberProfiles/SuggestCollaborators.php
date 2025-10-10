<?php

namespace App\Actions\MemberProfiles;

use App\Models\MemberProfile;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class SuggestCollaborators
{
    use AsAction;

    /**
     * Suggest profiles for collaboration based on genres and skills.
     */
    public function handle(MemberProfile $profile, int $limit = 10): Collection
    {
        $userGenres = $profile->genres;
        $userSkills = $profile->skills;

        if (empty($userGenres) && empty($userSkills)) {
            return new Collection;
        }

        $query = MemberProfile::where('id', '!=', $profile->id)
            ->where('visibility', '!=', 'private');

        // Find profiles with matching genres or complementary skills
        if (!empty($userGenres)) {
            $query->withAnyTags($userGenres, 'genre');
        }

        return $query->with(['user', 'tags'])
            ->limit($limit)
            ->get();
    }
}
