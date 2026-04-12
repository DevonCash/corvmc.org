<?php

namespace CorvMC\Moderation\Actions\Trust;

use CorvMC\Moderation\Services\TrustService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use TrustService::penalizeViolation() instead
 * This action is maintained for backward compatibility only.
 * New code should use the TrustService directly.
 */
class PenalizeViolation
{
    use AsAction;

    /**
     * @deprecated Use TrustService::penalizeViolation() instead
     */
    public function handle(...$args)
    {
        return app(TrustService::class)->penalizeViolation(...$args);
    }
}
