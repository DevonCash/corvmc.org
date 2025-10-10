<?php

namespace App\Actions\Sponsors;

use App\Models\Sponsor;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class GetEventLogoSponsors
{
    use AsAction;

    /**
     * Get sponsors eligible for event logo display
     */
    public function handle()
    {
        return Cache::remember('sponsors.event_logo', 3600, function () {
            return Sponsor::active()
                ->whereIn('tier', [
                    Sponsor::TIER_MELODY,
                    Sponsor::TIER_RHYTHM,
                    Sponsor::TIER_CRESCENDO
                ])
                ->ordered()
                ->get();
        });
    }
}
