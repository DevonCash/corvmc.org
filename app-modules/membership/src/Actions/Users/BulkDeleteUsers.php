<?php

namespace CorvMC\Membership\Actions\Users;

use CorvMC\Membership\Services\UserManagementService;

/**
 * @deprecated Use UserManagementService::bulkDelete() instead
 * This action is maintained for backward compatibility only.
 * New code should use the UserManagementService directly.
 */
class BulkDeleteUsers
{
    /**
     * @deprecated Use UserManagementService::bulkDelete() instead
     */
    public function handle(...$args)
    {
        return app(UserManagementService::class)->bulkDelete(...$args);
    }
}
