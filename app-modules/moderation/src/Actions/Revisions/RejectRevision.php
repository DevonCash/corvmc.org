<?php

namespace CorvMC\Moderation\Actions\Revisions;

use CorvMC\Moderation\Services\RevisionService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use RevisionService::reject() instead
 * This action is maintained for backward compatibility only.
 * New code should use the RevisionService directly.
 */
class RejectRevision
{
    use AsAction;

    /**
     * @deprecated Use RevisionService::reject() instead
     */
    public function handle(...$args)
    {
        return app(RevisionService::class)->reject(...$args);
    }
}
