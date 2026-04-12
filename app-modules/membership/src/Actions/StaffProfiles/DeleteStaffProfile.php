<?php

namespace CorvMC\Membership\Actions\StaffProfiles;

use CorvMC\Membership\Services\StaffProfileService;

/**
 * @deprecated Use StaffProfileService::delete() instead
 * This action is maintained for backward compatibility only.
 * New code should use the StaffProfileService directly.
 */
class DeleteStaffProfile
{
    /**
     * @deprecated Use StaffProfileService::delete() instead
     */
    public function handle(...$args)
    {
        return app(StaffProfileService::class)->delete(...$args);
    }
}
