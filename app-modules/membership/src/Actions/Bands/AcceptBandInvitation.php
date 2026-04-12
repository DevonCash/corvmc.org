<?php

namespace CorvMC\Membership\Actions\Bands;

use CorvMC\Membership\Services\BandService;

/**
 * @deprecated Use BandService::acceptInvitation() instead
 * This action is maintained for backward compatibility only.
 * New code should use the BandService directly.
 */
class AcceptBandInvitation
{
    /**
     * @deprecated Use BandService::acceptInvitation() instead
     */
    public function handle(...$args)
    {
        return app(BandService::class)->acceptInvitation(...$args);
    }
}
