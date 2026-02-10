<?php

namespace CorvMC\Membership\Actions\MemberProfiles;

use CorvMC\Membership\Events\MemberProfileUpdated as MemberProfileUpdatedEvent;
use CorvMC\Membership\Models\MemberProfile;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateMemberProfile
{
    use AsAction;

    /**
     * Update a member profile.
     */
    private const LOGGED_FIELDS = ['bio', 'hometown', 'visibility'];

    public function handle(MemberProfile $profile, array $data): MemberProfile
    {
        $originalData = $profile->toArray();

        $profile = DB::transaction(function () use ($profile, $data) {
            $profile->update($data);

            // Handle tag updates
            if (isset($data['skills'])) {
                UpdateSkills::run($profile, $data['skills']);
            }

            if (isset($data['genres'])) {
                UpdateGenres::run($profile, $data['genres']);
            }

            if (isset($data['influences'])) {
                UpdateInfluences::run($profile, $data['influences']);
            }

            // Handle media updates
            if (isset($data['avatar'])) {
                $profile->clearMediaCollection('avatar');
                $profile->addMediaFromRequest('avatar')
                    ->toMediaCollection('avatar');
            }

            return $profile->fresh();
        });

        $changedFields = array_keys(array_filter(
            array_intersect_key($profile->toArray(), array_flip(self::LOGGED_FIELDS)),
            fn ($value, $key) => ($originalData[$key] ?? null) !== $value,
            ARRAY_FILTER_USE_BOTH,
        ));

        if (! empty($changedFields)) {
            $oldValues = array_intersect_key($originalData, array_flip($changedFields));
            MemberProfileUpdatedEvent::dispatch($profile, $changedFields, $oldValues);
        }

        return $profile;
    }
}
