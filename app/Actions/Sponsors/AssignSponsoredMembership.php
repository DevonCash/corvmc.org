<?php

namespace App\Actions\Sponsors;

use App\Models\Sponsor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class AssignSponsoredMembership
{
    use AsAction;

    /**
     * Assign a sponsored membership to a user.
     *
     * @throws \Exception if sponsor has no available slots or user is already sponsored by this sponsor
     */
    public function handle(Sponsor $sponsor, User $user): void
    {
        // Check if sponsor has available slots
        if (! $sponsor->hasAvailableSlots()) {
            throw new \Exception(
                "Cannot assign sponsored membership: {$sponsor->name} has no available slots. ".
                "{$sponsor->usedSlots()} of {$sponsor->sponsored_memberships} slots are in use."
            );
        }

        // Check if user is already sponsored by this sponsor
        if ($sponsor->sponsoredMembers()->where('user_id', $user->id)->exists()) {
            throw new \Exception(
                "Cannot assign sponsored membership: {$user->name} is already sponsored by {$sponsor->name}."
            );
        }

        DB::transaction(function () use ($sponsor, $user) {
            $sponsor->sponsoredMembers()->attach($user->id);
        });
    }
}
