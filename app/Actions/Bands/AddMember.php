<?php

namespace App\Actions\Bands;

use App\Exceptions\BandException;
use App\Models\Band;
use App\Models\BandMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class AddMember
{
    use AsAction;

    /**
     * Add a member directly to a band (without invitation).
     */
    public function handle(
        Band $band,
        ?User $user = null,
        array $data = [],
    ): void {
        // Check if user is already a member by looking at pivot table
        if ($user && $band->memberships()->active()->where('user_id', $user->id)->exists()) {
            throw BandException::userAlreadyMember();
        }

        $role = $data['role'] ?? 'member';
        $position = $data['position'] ?? null;
        $displayName = $data['display_name'] ?? null;

        DB::transaction(function () use ($band, $user, $role, $position, $displayName) {
            // If user is null, create a guest member entry (non-CMC member)
            if (is_null($user)) {
                BandMember::create([
                    'band_profile_id' => $band->id,
                    'user_id' => null,
                    'name' => $displayName ?? 'Guest Member',
                    'role' => $role,
                    'position' => $position,
                    'status' => 'active',
                    'invited_at' => now(),
                ]);
            } else {
                // Add member to pivot table (for tracking purposes)
                $band->members()->attach($user->id, [
                    'role' => $role,
                    'position' => $position,
                    'name' => $displayName ?? $user->name,
                    'status' => 'active',
                    'invited_at' => now(),
                ]);
            }
        });
    }
}
