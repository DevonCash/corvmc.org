<?php

namespace CorvMC\Membership\Actions\MemberProfiles;

use CorvMC\Membership\Services\MemberProfileService;

/**
 * @deprecated Use MemberProfileService::updateGenres() instead
 * This action is maintained for backward compatibility only.
 * New code should use the MemberProfileService directly.
 */
class UpdateGenres
{
    /**
     * @deprecated Use MemberProfileService::updateGenres() instead
     */
    public function handle(...$args)
    {
        return app(MemberProfileService::class)->updateGenres(...$args);
    }
}
