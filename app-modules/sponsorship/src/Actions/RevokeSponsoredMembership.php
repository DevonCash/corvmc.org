<?php

namespace CorvMC\Sponsorship\Actions;

use App\Models\User;
use CorvMC\Sponsorship\Models\Sponsor;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RevokeSponsoredMembership
{
    use AsAction;

    /**
     * Revoke a sponsored membership from a user.
     *
     * @throws \Exception if user is not sponsored by this sponsor
     */
    public function handle(Sponsor $sponsor, User $user): void
    {
        // Check if user is sponsored by this sponsor
        if (! $sponsor->sponsoredMembers()->where('user_id', $user->id)->exists()) {
            throw new \Exception(
                "Cannot revoke sponsored membership: {$user->name} is not sponsored by {$sponsor->name}."
            );
        }

        DB::transaction(function () use ($sponsor, $user) {
            $sponsor->sponsoredMembers()->detach($user->id);
        });
    }
}
