<?php

namespace CorvMC\Moderation\Actions\Trust;

use CorvMC\Moderation\Services\TrustService;

/**
 * @deprecated Use TrustService::awardSuccessfulContent() instead
 * This action is maintained for backward compatibility only.
 * New code should use the TrustService directly.
 */
class AwardSuccessfulContent
{
    /**
     * @deprecated Use TrustService::awardSuccessfulContent() instead
     */
    public function handle(...$args)
    {
        return app(TrustService::class)->awardSuccessfulContent(...$args);
    }
}
