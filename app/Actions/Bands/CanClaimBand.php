<?php

namespace App\Actions\Bands;

use App\Models\Band;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class CanClaimBand
{
    use AsAction;

    /**
     * Check if a user can claim a band (touring band without owner).
     */
    public function handle(Band $band, User $user): bool
    {
        return $band->is_touring_band && $band->owner_id === null && $user->can('create', Band::class);
    }
}
