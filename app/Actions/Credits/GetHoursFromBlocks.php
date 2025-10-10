<?php

namespace App\Actions\Credits;

use Lorisleiva\Actions\Concerns\AsAction;

class GetHoursFromBlocks
{
    use AsAction;

    public const DEFAULT_MINUTES_PER_BLOCK = 30;

    /**
     * Convert blocks to hours for display.
     *
     * Uses setting for minutes per block, with fallback to 30 minutes.
     */
    public function handle(int $blocks): float
    {
        $minutesPerBlock = config('reservation.minutes_per_block', self::DEFAULT_MINUTES_PER_BLOCK);

        return ($blocks * $minutesPerBlock) / 60;
    }
}
