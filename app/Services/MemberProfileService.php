<?php

namespace App\Services;

use App\Models\MemberProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class MemberProfileService
{
    /**
     * Create a new member profile.
     */
    public function createMemberProfile(array $data): MemberProfile
    {
        return DB::transaction(function () use ($data) {
            $profile = MemberProfile::create($data);

            // Handle tags if provided
            if (isset($data['skills'])) {
                foreach ($data['skills'] as $skill) {
                    $profile->attachTag($skill, 'skill');
                }
            }

            if (isset($data['genres'])) {
                foreach ($data['genres'] as $genre) {
                    $profile->attachTag($genre, 'genre');
                }
            }

            if (isset($data['influences'])) {
                foreach ($data['influences'] as $influence) {
                    $profile->attachTag($influence, 'influence');
                }
            }

            // Handle media uploads if provided
            if (isset($data['avatar'])) {
                $profile->addMediaFromRequest('avatar')
                    ->toMediaCollection('avatar');
            }

            return $profile;
        });
    }

    /**
     * Update a member profile.
     */
    public function updateMemberProfile(MemberProfile $profile, array $data): MemberProfile
    {
        return DB::transaction(function () use ($profile, $data) {
            $profile->update($data);

            // Handle tag updates
            if (isset($data['skills'])) {
                $this->updateSkills($profile, $data['skills']);
            }

            if (isset($data['genres'])) {
                $this->updateGenres($profile, $data['genres']);
            }

            if (isset($data['influences'])) {
                $this->updateInfluences($profile, $data['influences']);
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

    /**
     * Delete a member profile.
     */
    public function deleteMemberProfile(MemberProfile $profile): bool
    {
        return DB::transaction(function () use ($profile) {
            // Clear all media
            $profile->clearMediaCollection('avatar');
            
            // Detach all tags
            $profile->detachTags();
            
            return $profile->delete();
        });
    }
    /**
     * Update member profile visibility.
     */
    public function updateVisibility(MemberProfile $profile, string $visibility): bool
    {
        if (! in_array($visibility, ['public', 'members', 'private'])) {
            return false;
        }

        $profile->update(['visibility' => $visibility]);

        return true;
    }

    /**
     * Update member profile skills.
     */
    public function updateSkills(MemberProfile $profile, array $skills): bool
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

    /**
     * Update member profile genres.
     */
    public function updateGenres(MemberProfile $profile, array $genres): bool
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

    /**
     * Update member profile influences.
     */
    public function updateInfluences(MemberProfile $profile, array $influences): bool
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

    /**
     * Set profile flags.
     */
    public function setFlags(MemberProfile $profile, array $flags): bool
    {
        DB::transaction(function () use ($profile, $flags) {
            // Remove all current flags
            $profile->flags()->delete();

            // Add new flags
            foreach ($flags as $flag) {
                $profile->flag($flag);
            }
        });

        return true;
    }

    /**
     * Search member profiles by skills, genres, or flags.
     */
    public function searchProfiles(
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

    /**
     * Get member directory statistics.
     */
    public function getDirectoryStats(): array
    {
        return [
            'total_members' => MemberProfile::count(),
            'public_profiles' => MemberProfile::where('visibility', 'public')->count(),
            'seeking_bands' => MemberProfile::withFlag('seeking_band')->count(),
            'available_for_session' => MemberProfile::withFlag('available_for_session')->count(),
            'top_skills' => $this->getTopTags('skill', 10),
            'top_genres' => $this->getTopTags('genre', 10),
        ];
    }

    /**
     * Get most popular tags by type.
     */
    protected function getTopTags(string $type, int $limit = 10): array
    {
        return DB::table('taggables')
            ->join('tags', 'tags.id', '=', 'taggables.tag_id')
            ->where('taggables.taggable_type', MemberProfile::class)
            ->where('tags.type', $type)
            ->select('tags.name', DB::raw('COUNT(*) as count'))
            ->groupBy('tags.name')
            ->orderBy('count', 'desc')
            ->limit($limit)
            ->pluck('count', 'name')
            ->toArray();
    }

    /**
     * Get profiles with specific flags.
     */
    public function getProfilesWithFlag(string $flag, ?User $viewingUser = null): Collection
    {
        $query = MemberProfile::withFlag($flag);

        // Apply visibility filter
        if (! $viewingUser) {
            $query->where('visibility', 'public');
        } elseif (! $viewingUser->can('view private member profiles')) {
            $query->where(function ($q) use ($viewingUser) {
                $q->where('visibility', 'public')
                    ->orWhere('user_id', $viewingUser->id)
                    ->orWhere('visibility', 'members');
            });
        }

        return $query->with(['user', 'tags'])->get();
    }

    /**
     * Suggest profiles for collaboration based on genres and skills.
     */
    public function suggestCollaborators(MemberProfile $profile, int $limit = 10): Collection
    {
        $userGenres = $profile->genres;
        $userSkills = $profile->skills;

        if (empty($userGenres) && empty($userSkills)) {
            return new Collection;
        }

        $query = MemberProfile::where('id', '!=', $profile->id)
            ->where('visibility', '!=', 'private');

        // Find profiles with matching genres or complementary skills
        if (! empty($userGenres)) {
            $query->withAnyTags($userGenres, 'genre');
        }

        return $query->with(['user', 'tags'])
            ->limit($limit)
            ->get();
    }
}
