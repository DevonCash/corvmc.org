<?php

namespace CorvMC\Moderation\Actions\Revisions;

use CorvMC\Moderation\Services\RevisionService;

/**
 * @deprecated Use RevisionService::apply() instead
 * This action is maintained for backward compatibility only.
 * New code should use the RevisionService directly.
 */
class ApplyRevision
{
    /**
     * @deprecated Use RevisionService::apply() instead
     */
    public function handle(...$args)
    {
        return app(RevisionService::class)->apply(...$args);
    }
}
