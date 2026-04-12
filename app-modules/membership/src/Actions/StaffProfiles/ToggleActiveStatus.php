<?php

namespace CorvMC\Membership\Actions\StaffProfiles;

use CorvMC\Membership\Services\StaffProfileService;

/**
 * @deprecated Use StaffProfileService::toggleActiveStatus() instead
 * This action is maintained for backward compatibility only.
 * New code should use the StaffProfileService directly.
 */
class ToggleActiveStatus
{
    /**
     * @deprecated Use StaffProfileService::toggleActiveStatus() instead
     */
    public function handle(...$args)
    {
        return app(StaffProfileService::class)->toggleActiveStatus(...$args);
    }
}
