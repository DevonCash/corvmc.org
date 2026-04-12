<?php

namespace CorvMC\Membership\Actions\Users;

use CorvMC\Membership\Services\UserManagementService;

/**
 * @deprecated Use UserManagementService::delete() instead
 * This action is maintained for backward compatibility only.
 * New code should use the UserManagementService directly.
 */
class DeleteUser
{
    /**
     * @deprecated Use UserManagementService::delete() instead
     */
    public function handle(...$args)
    {
        return app(UserManagementService::class)->delete(...$args);
    }
}
