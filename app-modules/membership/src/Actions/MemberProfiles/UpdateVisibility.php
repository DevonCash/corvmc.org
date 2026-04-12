<?php

namespace CorvMC\Membership\Actions\MemberProfiles;

use CorvMC\Membership\Services\MemberProfileService;

/**
 * @deprecated Use MemberProfileService::updateVisibility() instead
 * This action is maintained for backward compatibility only.
 * New code should use the MemberProfileService directly.
 */
class UpdateVisibility
{
    /**
     * @deprecated Use MemberProfileService::updateVisibility() instead
     */
    public function handle(...$args)
    {
        return app(MemberProfileService::class)->updateVisibility(...$args);
    }
}
