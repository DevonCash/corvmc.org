<?php

namespace CorvMC\Membership\Actions\MemberProfiles;

use CorvMC\Membership\Services\MemberProfileService;

/**
 * @deprecated Use MemberProfileService::updateInfluences() instead
 * This action is maintained for backward compatibility only.
 * New code should use the MemberProfileService directly.
 */
class UpdateInfluences
{
    /**
     * @deprecated Use MemberProfileService::updateInfluences() instead
     */
    public function handle(...$args)
    {
        return app(MemberProfileService::class)->updateInfluences(...$args);
    }
}
