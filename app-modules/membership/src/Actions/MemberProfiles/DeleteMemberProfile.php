<?php

namespace CorvMC\Membership\Actions\MemberProfiles;

use CorvMC\Membership\Services\MemberProfileService;

/**
 * @deprecated Use MemberProfileService::delete() instead
 * This action is maintained for backward compatibility only.
 * New code should use the MemberProfileService directly.
 */
class DeleteMemberProfile
{
    /**
     * @deprecated Use MemberProfileService::delete() instead
     */
    public function handle(...$args)
    {
        return app(MemberProfileService::class)->delete(...$args);
    }
}
