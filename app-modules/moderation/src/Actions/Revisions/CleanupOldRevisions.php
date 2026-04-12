<?php

namespace CorvMC\Moderation\Actions\Revisions;

use CorvMC\Moderation\Services\RevisionService;

/**
 * @deprecated Use RevisionService::cleanupOldRevisions() instead
 * This action is maintained for backward compatibility only.
 * New code should use the RevisionService directly.
 */
class CleanupOldRevisions
{
    /**
     * @deprecated Use RevisionService::cleanupOldRevisions() instead
     */
    public function handle(...$args)
    {
        return app(RevisionService::class)->cleanupOldRevisions(...$args);
    }
}
