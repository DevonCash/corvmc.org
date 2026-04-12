<?php

namespace CorvMC\Moderation\Actions\Revisions;

use CorvMC\Moderation\Services\RevisionService;

/**
 * @deprecated Use RevisionService::handleSubmission() instead
 * This action is maintained for backward compatibility only.
 * New code should use the RevisionService directly.
 */
class HandleRevisionSubmission
{
    /**
     * @deprecated Use RevisionService::handleSubmission() instead
     */
    public function handle(...$args)
    {
        return app(RevisionService::class)->handleSubmission(...$args);
    }
}
