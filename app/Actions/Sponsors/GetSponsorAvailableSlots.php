<?php

namespace App\Actions\Sponsors;

use App\Models\Sponsor;
use Lorisleiva\Actions\Concerns\AsAction;

class GetSponsorAvailableSlots
{
    use AsAction;

    /**
     * Get sponsor's slot allocation details.
     *
     * @return array{total: int, used: int, available: int, has_available: bool}
     */
    public function handle(Sponsor $sponsor): array
    {
        $total = $sponsor->sponsored_memberships;
        $used = $sponsor->usedSlots();
        $available = $sponsor->availableSlots();

        return [
            'total' => $total,
            'used' => $used,
            'available' => $available,
            'has_available' => $sponsor->hasAvailableSlots(),
        ];
    }
}
