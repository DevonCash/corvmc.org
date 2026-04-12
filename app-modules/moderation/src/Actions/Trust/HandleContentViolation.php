<?php

namespace CorvMC\Moderation\Actions\Trust;

use CorvMC\Moderation\Services\TrustService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use TrustService::handleContentViolation() instead
 * This action is maintained for backward compatibility only.
 * New code should use the TrustService directly.
 */
class HandleContentViolation
{
    use AsAction;

    /**
     * @deprecated Use TrustService::handleContentViolation() instead
     */
    public function handle(...$args)
    {
        return app(TrustService::class)->handleContentViolation(...$args);
    }
}
