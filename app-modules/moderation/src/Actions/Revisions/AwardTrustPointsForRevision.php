<?php

namespace CorvMC\Moderation\Actions\Revisions;

use CorvMC\Moderation\Services\TrustService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use TrustService::awardPoints() instead
 * This action is maintained for backward compatibility only.
 * New code should use the TrustService directly.
 */
class AwardTrustPointsForRevision
{
    use AsAction;

    /**
     * @deprecated Use TrustService::awardPoints() instead
     */
    public function handle(...$args)
    {
        return app(TrustService::class)->awardPoints(...$args);
    }
}
