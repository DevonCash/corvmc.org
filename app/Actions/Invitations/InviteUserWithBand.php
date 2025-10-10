<?php

namespace App\Actions\Invitations;

use App\Facades\BandService;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\BandOwnershipInvitationNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Lorisleiva\Actions\Concerns\AsAction;

class InviteUserWithBand
{
    use AsAction;

    /**
     * Invite a user to join CMC and create/own a band.
     */
    public function handle(string $email, string $bandName, array $bandData = []): Invitation
    {
        return DB::transaction(function () use ($email, $bandName, $bandData) {
            $band_role = $bandData['band_role'] ?? 'admin';

            if (User::where('email', $email)->exists()) {
                throw new \Exception('User with this email already exists.');
            }

            // Create band without an owner initially (will be set when invitation is accepted)
            $band = BandService::createBand([
                'name' => $bandName,
                'owner_id' => null, // Will be set when invitation is accepted
                'visibility' => 'members',
                'status' => 'pending_owner_verification',
            ]);

            // Generate invitation with band data
            $invitation = GenerateInvitation::run($email, [
                'band_id' => $band->id,
                'band_role' => $band_role,
                'message' => "You've been invited to join Corvallis Music Collective and create the band '{$bandName}'!"
            ]);

            // Send special band ownership invitation
            Notification::route('mail', $email)
                ->notify(new BandOwnershipInvitationNotification(
                    Auth::user(), // inviter user
                    $band,
                    $invitation->token,
                ));

            $invitation->markAsSent();

            return $invitation;
        });
    }
}
