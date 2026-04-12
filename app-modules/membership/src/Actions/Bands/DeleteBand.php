<?php

namespace CorvMC\Membership\Actions\Bands;

use CorvMC\Membership\Services\BandService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use BandService::delete() instead
 * This action is maintained for backward compatibility only.
 * New code should use the BandService directly.
 */
class DeleteBand
{
    use AsAction;

    /**
     * @deprecated Use BandService::delete() instead
     */
    public function handle(...$args)
    {
        return app(BandService::class)->delete(...$args);
    }
}
