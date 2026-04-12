<?php

namespace CorvMC\Moderation\Actions\SpamPrevention;

use CorvMC\Moderation\Services\SpamPreventionService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use SpamPreventionService::scanUsersForSpam() instead
 * This action is maintained for backward compatibility only.
 * New code should use the SpamPreventionService directly.
 */
class ScanUsersForSpam
{
    use AsAction;

    /**
     * @deprecated Use SpamPreventionService::scanUsersForSpam() instead
     */
    public function handle(...$args)
    {
        return app(SpamPreventionService::class)->scanUsersForSpam(...$args);
    }
}
