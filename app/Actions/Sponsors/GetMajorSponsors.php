<?php

namespace App\Actions\Sponsors;

use App\Models\Sponsor;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class GetMajorSponsors
{
    use AsAction;

    /**
     * Get major sponsors (Rhythm and Crescendo tiers) for home page display
     */
    public function handle()
    {
        return Cache::remember('sponsors.major', 3600, function () {
            return Sponsor::active()->major()->ordered()->get();
        });
    }
}
