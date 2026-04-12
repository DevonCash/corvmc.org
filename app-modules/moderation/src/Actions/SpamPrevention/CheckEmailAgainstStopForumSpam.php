<?php

namespace CorvMC\Moderation\Actions\SpamPrevention;

use CorvMC\Moderation\Services\SpamPreventionService;

/**
 * @deprecated Use SpamPreventionService::checkEmailAgainstStopForumSpam() instead
 * This action is maintained for backward compatibility only.
 * New code should use the SpamPreventionService directly.
 */
class CheckEmailAgainstStopForumSpam
{
    /**
     * @deprecated Use SpamPreventionService::checkEmailAgainstStopForumSpam() instead
     */
    public function handle(...$args)
    {
        return app(SpamPreventionService::class)->checkEmailAgainstStopForumSpam(...$args);
    }
}
