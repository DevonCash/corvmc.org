<?php

namespace CorvMC\Membership\Actions\StaffProfiles;

use CorvMC\Membership\Services\StaffProfileService;

/**
 * @deprecated Use StaffProfileService::getStaffProfileStats() instead
 * This action is maintained for backward compatibility only.
 * New code should use the StaffProfileService directly.
 */
class GetStaffProfileStats
{
    /**
     * @deprecated Use StaffProfileService::getStaffProfileStats() instead
     */
    public function handle(...$args)
    {
        return app(StaffProfileService::class)->getStaffProfileStats(...$args);
    }
}
