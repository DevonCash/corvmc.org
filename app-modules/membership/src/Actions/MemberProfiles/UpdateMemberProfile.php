<?php

namespace CorvMC\Membership\Actions\MemberProfiles;

use CorvMC\Membership\Services\MemberProfileService;

/**
 * @deprecated Use MemberProfileService::update() instead
 * This action is maintained for backward compatibility only.
 * New code should use the MemberProfileService directly.
 */
class UpdateMemberProfile
{
    /**
     * @deprecated Use MemberProfileService::update() instead
     */
    public function handle(...$args)
    {
        return app(MemberProfileService::class)->update(...$args);
    }
}
