<?php

namespace CorvMC\Membership\Actions\StaffProfiles;

use CorvMC\Membership\Services\StaffProfileService;

/**
 * @deprecated Use StaffProfileService::linkToUser() instead
 * This action is maintained for backward compatibility only.
 * New code should use the StaffProfileService directly.
 */
class LinkToUser
{
    /**
     * @deprecated Use StaffProfileService::linkToUser() instead
     */
    public function handle(...$args)
    {
        return app(StaffProfileService::class)->linkToUser(...$args);
    }
}
