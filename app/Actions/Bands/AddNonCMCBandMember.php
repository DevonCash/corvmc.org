<?php

namespace App\Actions\Bands;

use App\Models\Band;
use App\Models\BandMember;
use Lorisleiva\Actions\Concerns\AsAction;

class AddNonCMCBandMember
{
    use AsAction;

    /**
     * Add a non-CMC member directly to the band (no user account).
     */
    public function handle(
        Band $band,
        string $name,
        string $role = 'member',
        ?string $position = null
    ): BandMember {
        return $band->memberships()->create([
            'user_id' => null,
            'role' => $role,
            'position' => $position,
            'name' => $name,
            'status' => 'active',
        ]);
    }
}
