<?php

namespace CorvMC\Membership\Actions\Users;

use CorvMC\Membership\Services\UserManagementService;

/**
 * @deprecated Use UserManagementService::bulkUpdate() instead
 * This action is maintained for backward compatibility only.
 * New code should use the UserManagementService directly.
 */
class BulkUpdateUsers
{
    /**
     * @deprecated Use UserManagementService::bulkUpdate() instead
     */
    public function handle(...$args)
    {
        return app(UserManagementService::class)->bulkUpdate(...$args);
    }
}
