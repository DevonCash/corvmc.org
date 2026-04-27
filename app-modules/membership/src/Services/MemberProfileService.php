<?php

namespace CorvMC\Membership\Services;

use App\Models\User;
use CorvMC\Membership\Events\MemberProfileCreated;
use CorvMC\Membership\Events\MemberProfileDeleted;
use CorvMC\Membership\Events\MemberProfileUpdated;
use CorvMC\Membership\Models\MemberProfile;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class MemberProfileService
{
    public function create(User $user, array $data): MemberProfile
    {
        $profile = DB::transaction(function () use ($user, $data) {
            $existing = MemberProfile::where('user_id', $user->id)->first();

            if ($existing) {
                if (! empty($data)) {
                    $genres = Arr::pull($data, 'genres');
                    $skills = Arr::pull($data, 'skills');
                    $influences = Arr::pull($data, 'influences');

                    $existing->update($data);

                    if ($genres !== null) {
                        $existing->syncTagsWithType($genres, 'genre');
                    }

                    if ($skills !== null) {
                        $existing->syncTagsWithType($skills, 'skill');
                    }

                    if ($influences !== null) {
                        $existing->syncTagsWithType($influences, 'influence');
                    }
                }

                return $existing->fresh();
            }

            $profile = MemberProfile::create(array_merge($data, [
                'user_id' => $user->id,
            ]));

            if (isset($data['genres'])) {
                $profile->attachTags($data['genres'], 'genre');
            }

            if (isset($data['skills'])) {
                $profile->attachTags($data['skills'], 'skill');
            }

            return $profile;
        });

        MemberProfileCreated::dispatch($profile);

        return $profile;
    }

    public function update(MemberProfile $profile, array $data): MemberProfile
    {
        $oldValues = collect($data)->reject(fn ($v, $k) => in_array($k, ['genres', 'skills', 'influences']))
            ->mapWithKeys(fn ($v, $k) => [$k => $profile->getOriginal($k)])
            ->toArray();
        $changedFields = array_keys($oldValues);

        $result = DB::transaction(function () use ($profile, $data) {
            $genres = Arr::pull($data, 'genres');
            $skills = Arr::pull($data, 'skills');
            $influences = Arr::pull($data, 'influences');

            $profile->update($data);

            if ($genres !== null) {
                $profile->syncTagsWithType($genres, 'genre');
            }

            if ($skills !== null) {
                $profile->syncTagsWithType($skills, 'skill');
            }

            if ($influences !== null) {
                $profile->syncTagsWithType($influences, 'influence');
            }

            return $profile->fresh();
        });

        if (! empty($changedFields)) {
            MemberProfileUpdated::dispatch($result, $changedFields, $oldValues);
        }

        return $result;
    }

    public function delete(MemberProfile $profile): bool
    {
        $result = $profile->delete();

        MemberProfileDeleted::dispatch($profile);

        return $result;
    }

    public function updateVisibility(MemberProfile $profile, mixed $visibility): MemberProfile
    {
        $profile->update(['visibility' => $visibility]);
        return $profile->fresh();
    }

    public function updateGenres(MemberProfile $profile, array $genres): void
    {
        $profile->syncTagsWithType($genres, 'genre');
    }

    public function updateSkills(MemberProfile $profile, array $skills): void
    {
        $profile->syncTagsWithType($skills, 'skill');
    }

    public function updateInfluences(MemberProfile $profile, array $influences): void
    {
        $profile->syncTagsWithType($influences, 'influence');
    }

    public function setFlags(MemberProfile $profile, array $flags): void
    {
        foreach ($flags as $flag => $value) {
            // Handle indexed arrays: ['is_teacher', 'is_professional'] means enable all
            if (is_int($flag)) {
                $profile->flag($value);
            } elseif ($value) {
                $profile->flag($flag);
            } else {
                $profile->unflag($flag);
            }
        }
    }

    public function searchProfiles(array $filters = []): Collection
    {
        $query = MemberProfile::query();
        
        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('display_name', 'like', '%'.$filters['search'].'%')
                  ->orWhere('bio', 'like', '%'.$filters['search'].'%');
            });
        }
        
        if (isset($filters['visibility'])) {
            $query->where('visibility', $filters['visibility']);
        }
        
        return $query->get();
    }

}