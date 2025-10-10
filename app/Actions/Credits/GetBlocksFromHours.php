<?php

namespace App\Actions\Credits;

use Lorisleiva\Actions\Concerns\AsAction;

class GetBlocksFromHours
{
    use AsAction;

    public const DEFAULT_MINUTES_PER_BLOCK = 30;

    /**
     * Convert hours to blocks for credit system.
     *
     * Rounds up to ensure users are charged for full blocks.
     * Uses setting for minutes per block, with fallback to 30 minutes.
     */
    public function handle(float $hours): int
    {
        $minutesPerBlock = config('reservation.minutes_per_block', self::DEFAULT_MINUTES_PER_BLOCK);

        return (int) ceil(($hours * 60) / $minutesPerBlock);
    }
}
