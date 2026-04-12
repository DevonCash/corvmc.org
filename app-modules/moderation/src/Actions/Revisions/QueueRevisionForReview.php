<?php

namespace CorvMC\Moderation\Actions\Revisions;

use CorvMC\Moderation\Services\RevisionService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use RevisionService::queueForReview() instead
 * This action is maintained for backward compatibility only.
 * New code should use the RevisionService directly.
 */
class QueueRevisionForReview
{
    use AsAction;

    /**
     * @deprecated Use RevisionService::queueForReview() instead
     */
    public function handle(...$args)
    {
        return app(RevisionService::class)->queueForReview(...$args);
    }
}
