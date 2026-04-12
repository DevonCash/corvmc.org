<?php

namespace CorvMC\Sponsorship\Actions;

use CorvMC\Sponsorship\Models\Sponsor;
use CorvMC\Sponsorship\Services\SponsorshipService;

/**
 * Get sponsor's slot allocation details.
 *
 * @deprecated Use SponsorshipService::getAvailableSlots() instead
 * This action is maintained for backward compatibility only.
 * New code should use the SponsorshipService directly.
 */
class GetSponsorAvailableSlots
{
    /**
     * Get sponsor's slot allocation details.
     *
     * @deprecated Use SponsorshipService::getAvailableSlots() instead
     * @return array{total: int, used: int, available: int, has_available: bool}
     */
    public function handle(Sponsor $sponsor): array
    {
        return app(SponsorshipService::class)->getAvailableSlots($sponsor);
    }
}
