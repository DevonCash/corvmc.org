<?php

namespace CorvMC\Sponsorship\Actions;

use App\Models\User;
use CorvMC\Sponsorship\Models\Sponsor;
use CorvMC\Sponsorship\Services\SponsorshipService;

/**
 * Assign a sponsored membership to a user.
 *
 * @deprecated Use SponsorshipService::assignMembership() instead
 * This action is maintained for backward compatibility only.
 * New code should use the SponsorshipService directly.
 */
class AssignSponsoredMembership
{
    /**
     * Assign a sponsored membership to a user.
     *
     * @deprecated Use SponsorshipService::assignMembership() instead
     * @throws \Exception if sponsor has no available slots or user is already sponsored by this sponsor
     */
    public function handle(Sponsor $sponsor, User $user): void
    {
        app(SponsorshipService::class)->assignMembership($sponsor, $user);
    }
}
