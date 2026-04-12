<?php

namespace CorvMC\Membership\Actions\StaffProfiles;

use CorvMC\Membership\Services\StaffProfileService;

/**
 * @deprecated Use StaffProfileService::unlinkFromUser() instead
 * This action is maintained for backward compatibility only.
 * New code should use the StaffProfileService directly.
 */
class UnlinkFromUser
{
    /**
     * @deprecated Use StaffProfileService::unlinkFromUser() instead
     */
    public function handle(...$args)
    {
        return app(StaffProfileService::class)->unlinkFromUser(...$args);
    }
}
