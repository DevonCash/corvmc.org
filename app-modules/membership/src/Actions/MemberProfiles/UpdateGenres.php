<?php

namespace CorvMC\Membership\Actions\MemberProfiles;

use CorvMC\Membership\Services\MemberProfileService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use MemberProfileService::updateGenres() instead
 * This action is maintained for backward compatibility only.
 * New code should use the MemberProfileService directly.
 */
class UpdateGenres
{
    use AsAction;

    /**
     * @deprecated Use MemberProfileService::updateGenres() instead
     */
    public function handle(...$args)
    {
        return app(MemberProfileService::class)->updateGenres(...$args);
    }
}
