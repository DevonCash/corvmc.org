<?php

namespace CorvMC\Membership\Actions\Users;

use CorvMC\Membership\Services\UserManagementService;

/**
 * @deprecated Use UserManagementService::getUsers() instead
 * This action is maintained for backward compatibility only.
 * New code should use the UserManagementService directly.
 */
class GetUsers
{
    /**
     * @deprecated Use UserManagementService::getUsers() instead
     */
    public function handle(...$args)
    {
        return app(UserManagementService::class)->getUsers(...$args);
    }
}
