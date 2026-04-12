<?php

namespace CorvMC\Membership\Actions\StaffProfiles;

use CorvMC\Membership\Services\StaffProfileService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use StaffProfileService::update() instead
 * This action is maintained for backward compatibility only.
 * New code should use the StaffProfileService directly.
 */
class UpdateStaffProfile
{
    use AsAction;

    /**
     * @deprecated Use StaffProfileService::update() instead
     */
    public function handle(...$args)
    {
        return app(StaffProfileService::class)->update(...$args);
    }
}
