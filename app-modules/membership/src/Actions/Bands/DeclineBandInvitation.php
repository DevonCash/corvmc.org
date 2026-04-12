<?php

namespace CorvMC\Membership\Actions\Bands;

use CorvMC\Membership\Services\BandService;

/**
 * @deprecated Use BandService::declineInvitation() instead
 * This action is maintained for backward compatibility only.
 * New code should use the BandService directly.
 */
class DeclineBandInvitation
{
    /**
     * @deprecated Use BandService::declineInvitation() instead
     */
    public function handle(...$args)
    {
        return app(BandService::class)->declineInvitation(...$args);
    }
}
