<?php

namespace CorvMC\Moderation\Actions\Trust;

use CorvMC\Moderation\Services\TrustService;

/**
 * @deprecated Use TrustService::handleContentViolation() instead
 * This action is maintained for backward compatibility only.
 * New code should use the TrustService directly.
 */
class HandleContentViolation
{
    /**
     * @deprecated Use TrustService::handleContentViolation() instead
     */
    public function handle(...$args)
    {
        return app(TrustService::class)->handleContentViolation(...$args);
    }
}
