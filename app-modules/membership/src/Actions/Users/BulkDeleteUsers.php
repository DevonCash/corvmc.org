<?php

namespace CorvMC\Membership\Actions\Users;

use CorvMC\Membership\Services\UserManagementService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use UserManagementService::bulkDelete() instead
 * This action is maintained for backward compatibility only.
 * New code should use the UserManagementService directly.
 */
class BulkDeleteUsers
{
    use AsAction;

    /**
     * @deprecated Use UserManagementService::bulkDelete() instead
     */
    public function handle(...$args)
    {
        return app(UserManagementService::class)->bulkDelete(...$args);
    }
}
