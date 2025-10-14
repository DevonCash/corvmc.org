<?php

namespace App\Actions\Bands;

use App\Models\Band;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class EnsureAdminMembership
{
    use AsAction;

    /**
     * Ensure user is an admin member of the band (add or update).
     */
    public function handle(Band $band, User $user): void
    {
        DB::transaction(function () use ($band, $user) {
            $existingMembership = $band->memberships()
                ->active()
                ->where('user_id', $user->id)
                ->first();

            if ($existingMembership) {
                // Update existing membership to admin
                $existingMembership->update(['role' => 'admin']);
            } else {
                // Add user as admin member
                AddBandMember::run($band, $user, ['role' => 'admin']);
            }
        });
    }
}
