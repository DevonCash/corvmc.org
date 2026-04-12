<?php

namespace CorvMC\Moderation\Actions\Trust;

use CorvMC\Moderation\Services\TrustService;

/**
 * @deprecated Use TrustService::bulkAwardPastContent() instead
 * This action is maintained for backward compatibility only.
 * New code should use the TrustService directly.
 */
class BulkAwardPastContent
{
    /**
     * @deprecated Use TrustService::bulkAwardPastContent() instead
     */
    public function handle(...$args)
    {
        return app(TrustService::class)->bulkAwardPastContent(...$args);
    }
}
