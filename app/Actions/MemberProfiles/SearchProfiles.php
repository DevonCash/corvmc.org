<?php

namespace App\Actions\MemberProfiles;

use App\Models\MemberProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class SearchProfiles
{
    use AsAction;

    /**
     * Search member profiles by skills, genres, or flags.
     */
    public function handle(
        ?string $query = null,
        ?array $skills = null,
        ?array $genres = null,
        ?array $flags = null,
        ?User $viewingUser = null
    ): Collection {
        $profilesQuery = MemberProfile::withoutGlobalScope(\App\Models\Scopes\MemberVisibilityScope::class);

        // Apply visibility filter based on viewing user
        if (! $viewingUser) {
            $profilesQuery->where('visibility', 'public');
        } elseif (! $viewingUser->can('view private member profiles')) {
            $profilesQuery->where(function ($q) use ($viewingUser) {
                $q->where('visibility', 'public')
                    ->orWhere('user_id', $viewingUser->id)
                    ->orWhere('visibility', 'members');
            });
        }

        // Search by name or bio
        if ($query) {
            $profilesQuery->whereHas('user', function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%");
            })->orWhere('bio', 'like', "%{$query}%");
        }

        // Filter by skills
        if ($skills && count($skills) > 0) {
            $profilesQuery->withAnyTags($skills, 'skill');
        }

        // Filter by genres
        if ($genres && count($genres) > 0) {
            $profilesQuery->withAnyTags($genres, 'genre');
        }

        // Filter by flags
        if ($flags && count($flags) > 0) {
            foreach ($flags as $flag) {
                $profilesQuery->withFlag($flag);
            }
        }

        return $profilesQuery->with(['user', 'tags'])
            ->limit(50)
            ->get();
    }
}
