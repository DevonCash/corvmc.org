<?php

namespace CorvMC\Moderation\Actions\Revisions;

use CorvMC\Moderation\Services\RevisionService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use RevisionService::autoApprove() instead
 * This action is maintained for backward compatibility only.
 * New code should use the RevisionService directly.
 */
class AutoApproveRevision
{
    use AsAction;

    /**
     * @deprecated Use RevisionService::autoApprove() instead
     */
    public function handle(...$args)
    {
        return app(RevisionService::class)->autoApprove(...$args);
    }
}
