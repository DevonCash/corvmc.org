<?php

namespace App\Actions\Sponsors;

use App\Models\Sponsor;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class GetActiveSponsors
{
    use AsAction;

    /**
     * Get all active sponsors grouped by tier
     */
    public function handle(): array
    {
        return Cache::remember('sponsors.active.grouped', 3600, function () {
            $sponsors = Sponsor::active()->ordered()->get();

            return [
                'crescendo' => $sponsors->where('tier', Sponsor::TIER_CRESCENDO),
                'rhythm' => $sponsors->where('tier', Sponsor::TIER_RHYTHM),
                'melody' => $sponsors->where('tier', Sponsor::TIER_MELODY),
                'harmony' => $sponsors->where('tier', Sponsor::TIER_HARMONY),
                'in_kind' => $sponsors->where('type', Sponsor::TYPE_IN_KIND),
            ];
        });
    }
}
