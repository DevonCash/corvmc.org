<?php

namespace CorvMC\Membership\Actions\StaffProfiles;

use CorvMC\Membership\Services\StaffProfileService;

/**
 * @deprecated Use StaffProfileService::bulkUpdateProfiles() instead
 * This action is maintained for backward compatibility only.
 * New code should use the StaffProfileService directly.
 */
class BulkUpdateProfiles
{
    /**
     * @deprecated Use StaffProfileService::bulkUpdateProfiles() instead
     */
    public function handle(...$args)
    {
        return app(StaffProfileService::class)->bulkUpdateProfiles(...$args);
    }
}
