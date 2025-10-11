<?php

namespace App\Actions\Bands;

use App\Exceptions\BandException;
use App\Models\Band;
use App\Models\User;
use App\Notifications\BandClaimedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\UnauthorizedException;
use Lorisleiva\Actions\Concerns\AsAction;

class ClaimBand
{
    use AsAction;

    /**
     * Claim ownership of a guest band.
     */
    public function handle(Band $band, User $user): void
    {
        if ($band->owner_id) {
            throw BandException::bandAlreadyHasOwner();
        }

        if (!$user->can('create bands')) {
            throw new UnauthorizedException('User does not have permission to claim bands.');
        }

        DB::transaction(function () use ($band, $user) {
            // Update band ownership
            $band->update([
                'owner_id' => $user->id,
                'status' => 'active'
            ]);

            // Ensure user is admin member of the band
            EnsureAdminMembership::run($band, $user);

            // Notify admins about the band being claimed
            $admins = User::role(['admin'])->get();
            Notification::send($admins, new BandClaimedNotification($band, $user));
        });
    }
}
