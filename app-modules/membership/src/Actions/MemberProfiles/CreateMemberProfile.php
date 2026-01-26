<?php

namespace CorvMC\Membership\Actions\MemberProfiles;

use CorvMC\Membership\Models\MemberProfile;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateMemberProfile
{
    use AsAction;

    /**
     * Create a new member profile.
     */
    public function handle(array $data): MemberProfile
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
}
