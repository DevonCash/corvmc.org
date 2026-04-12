<?php

namespace CorvMC\Moderation\Actions\Trust;

use CorvMC\Moderation\Services\TrustService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use TrustService::bulkAwardPastContent() instead
 * This action is maintained for backward compatibility only.
 * New code should use the TrustService directly.
 */
class BulkAwardPastContent
{
    use AsAction;

    /**
     * @deprecated Use TrustService::bulkAwardPastContent() instead
     */
    public function handle(...$args)
    {
        return app(TrustService::class)->bulkAwardPastContent(...$args);
    }
}
