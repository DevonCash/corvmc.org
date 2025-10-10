<?php

namespace App\Actions\Bands;

use App\Exceptions\BandException;
use App\Models\Band;
use App\Models\User;
use App\Notifications\BandInvitationNotification;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class InviteMember
{
    use AsAction;

    /**
     * Invite a user to join a band.
     */
    public function handle(
        Band $band,
        User $user,
        string $role = 'member',
        ?string $position = null,
        ?string $displayName = null
    ): void {
        if ($band->memberships()->active()->where('user_id', $user->id)->exists()) {
            throw BandException::userAlreadyMember();
        }

        if ($band->memberships()->invited()->where('user_id', $user->id)->exists()) {
            throw BandException::userAlreadyInvited();
        }

        DB::transaction(function () use ($band, $user, $role, $position, $displayName) {
            // Add invitation to pivot table
            $band->members()->attach($user->id, [
                'role' => $role,
                'position' => $position,
                'name' => $displayName ?? $user->name,
                'status' => 'invited',
                'invited_at' => now(),
            ]);
        });
        // Send notification
        $user->notify(new BandInvitationNotification($band, $role, $position));
    }
}
