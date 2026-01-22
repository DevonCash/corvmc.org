<?php

namespace CorvMC\Membership\Actions\MemberProfiles;

use App\Models\MemberProfile;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class SetFlags
{
    use AsAction;

    /**
     * Set profile flags.
     */
    public function handle(MemberProfile $profile, array $flags): bool
    {
        DB::transaction(function () use ($profile, $flags) {
            // Remove all current flags
            $profile->flags()->delete();

            // Add new flags
            foreach ($flags as $flag) {
                $profile->flag($flag);
            }
        });

        return true;
    }
}
