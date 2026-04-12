<?php

namespace CorvMC\Membership\Actions\Bands;

use CorvMC\Membership\Services\BandService;

/**
 * @deprecated Use BandService::update() instead
 * This action is maintained for backward compatibility only.
 * New code should use the BandService directly.
 */
class UpdateBand
{
    /**
     * @deprecated Use BandService::update() instead
     */
    public function handle(...$args)
    {
        return app(BandService::class)->update(...$args);
    }
}
