<?php

namespace CorvMC\Membership\Actions\MemberProfiles;

use CorvMC\Membership\Services\MemberProfileService;

/**
 * @deprecated Use MemberProfileService::getDirectoryStats() instead
 * This action is maintained for backward compatibility only.
 * New code should use the MemberProfileService directly.
 */
class GetDirectoryStats
{
    /**
     * @deprecated Use MemberProfileService::getDirectoryStats() instead
     */
    public function handle(...$args)
    {
        return app(MemberProfileService::class)->getDirectoryStats(...$args);
    }
}
