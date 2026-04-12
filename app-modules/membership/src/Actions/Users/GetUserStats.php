<?php

namespace CorvMC\Membership\Actions\Users;

use CorvMC\Membership\Services\UserManagementService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use UserManagementService::getUserStats() instead
 * This action is maintained for backward compatibility only.
 * New code should use the UserManagementService directly.
 */
class GetUserStats
{
    use AsAction;

    /**
     * @deprecated Use UserManagementService::getUserStats() instead
     */
    public function handle(...$args)
    {
        return app(UserManagementService::class)->getUserStats(...$args);
    }
}
