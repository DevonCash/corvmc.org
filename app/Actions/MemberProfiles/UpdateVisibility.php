<?php

namespace App\Actions\MemberProfiles;

use App\Models\MemberProfile;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateVisibility
{
    use AsAction;

    /**
     * Update member profile visibility.
     */
    public function handle(MemberProfile $profile, string $visibility): bool
    {
        if (!in_array($visibility, ['public', 'members', 'private'])) {
            return false;
        }

        $profile->update(['visibility' => $visibility]);

        return true;
    }
}
