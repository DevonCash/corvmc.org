<?php

namespace CorvMC\Sponsorship\Actions;

use App\Models\User;
use CorvMC\Sponsorship\Models\Sponsor;
use CorvMC\Sponsorship\Services\SponsorshipService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Revoke a sponsored membership from a user.
 *
 * @deprecated Use SponsorshipService::revokeMembership() instead
 * This action is maintained for backward compatibility only.
 * New code should use the SponsorshipService directly.
 */
class RevokeSponsoredMembership
{
    use AsAction;

    /**
     * Revoke a sponsored membership from a user.
     *
     * @deprecated Use SponsorshipService::revokeMembership() instead
     * @throws \Exception if user is not sponsored by this sponsor
     */
    public function handle(Sponsor $sponsor, User $user): void
    {
        app(SponsorshipService::class)->revokeMembership($sponsor, $user);
    }
}
