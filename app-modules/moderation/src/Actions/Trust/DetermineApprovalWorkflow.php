<?php

namespace CorvMC\Moderation\Actions\Trust;

use CorvMC\Moderation\Services\TrustService;

/**
 * @deprecated Use TrustService::determineApprovalWorkflow() instead
 * This action is maintained for backward compatibility only.
 * New code should use the TrustService directly.
 */
class DetermineApprovalWorkflow
{
    /**
     * @deprecated Use TrustService::determineApprovalWorkflow() instead
     */
    public function handle(...$args)
    {
        return app(TrustService::class)->determineApprovalWorkflow(...$args);
    }
}
