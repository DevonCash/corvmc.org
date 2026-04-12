<?php

namespace CorvMC\Membership\Actions\StaffProfiles;

use CorvMC\Membership\Services\StaffProfileService;

/**
 * @deprecated Use StaffProfileService::create() instead
 * This action is maintained for backward compatibility only.
 * New code should use the StaffProfileService directly.
 */
class CreateStaffProfile
{
    /**
     * @deprecated Use StaffProfileService::create() instead
     */
    public function handle(...$args)
    {
        return app(StaffProfileService::class)->create(...$args);
    }
}
