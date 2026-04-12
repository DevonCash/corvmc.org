<?php

namespace CorvMC\Membership\Actions\Bands;

use CorvMC\Membership\Services\BandService;

/**
 * @deprecated Use BandService::cancelInvitation() instead
 * This action is maintained for backward compatibility only.
 * New code should use the BandService directly.
 */
class CancelBandInvitation
{
    /**
     * @deprecated Use BandService::cancelInvitation() instead
     */
    public function handle(...$args)
    {
        return app(BandService::class)->cancelInvitation(...$args);
    }
}
