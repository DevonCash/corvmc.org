<?php

namespace CorvMC\Membership\Actions\MemberProfiles;

use CorvMC\Moderation\Enums\Visibility;
use CorvMC\Membership\Models\MemberProfile;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateVisibility
{
    use AsAction;

    /**
     * Update member profile visibility.
     *
     * @throws \InvalidArgumentException
     */
    public function handle(MemberProfile $profile, Visibility|string $visibility): MemberProfile
    {
        // Convert string to enum if needed
        if (is_string($visibility)) {
            $visibility = Visibility::tryFrom($visibility);

            if ($visibility === null) {
                throw new \InvalidArgumentException(
                    'Invalid visibility value. Must be one of: public, members, private'
                );
            }
        }

        $profile->update(['visibility' => $visibility]);

        return $profile;
    }
}
