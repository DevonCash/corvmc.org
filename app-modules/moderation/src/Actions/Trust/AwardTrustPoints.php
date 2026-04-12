<?php

namespace CorvMC\Moderation\Actions\Trust;

use CorvMC\Moderation\Services\TrustService;

/**
 * @deprecated Use TrustService::awardPoints() instead
 * This action is maintained for backward compatibility only.
 * New code should use the TrustService directly.
 */
class AwardTrustPoints
{
    /**
     * @deprecated Use TrustService::awardPoints() instead
     */
    public function handle(...$args)
    {
        return app(TrustService::class)->awardPoints(...$args);
    }
}
