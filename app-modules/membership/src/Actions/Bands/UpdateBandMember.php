<?php

namespace CorvMC\Membership\Actions\Bands;

use CorvMC\Membership\Services\BandService;

/**
 * @deprecated Use BandService::updateMember() instead
 * This action is maintained for backward compatibility only.
 * New code should use the BandService directly.
 */
class UpdateBandMember
{
    /**
     * @deprecated Use BandService::updateMember() instead
     */
    public function handle(...$args)
    {
        return app(BandService::class)->updateMember(...$args);
    }
}
