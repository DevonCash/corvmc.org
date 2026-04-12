<?php

namespace CorvMC\Moderation\Actions\Revisions;

use CorvMC\Moderation\Services\RevisionService;

/**
 * @deprecated Use RevisionService::bulkApproveFromTrustedUsers() instead
 * This action is maintained for backward compatibility only.
 * New code should use the RevisionService directly.
 */
class BulkApproveFromTrustedUsers
{
    /**
     * @deprecated Use RevisionService::bulkApproveFromTrustedUsers() instead
     */
    public function handle(...$args)
    {
        return app(RevisionService::class)->bulkApproveFromTrustedUsers(...$args);
    }
}
