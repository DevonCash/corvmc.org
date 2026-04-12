<?php

namespace CorvMC\Membership\Actions\Bands;

use CorvMC\Membership\Services\BandService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use BandService::create() instead
 * This action is maintained for backward compatibility only.
 * New code should use the BandService directly.
 */
class CreateBand
{
    use AsAction;

    /**
     * @deprecated Use BandService::create() instead
     */
    public function handle(...$args)
    {
        return app(BandService::class)->create(...$args);
    }
}
