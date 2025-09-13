<?php

namespace App\Services;

use App\Facades\BandService;
use App\Models\Band;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\UserInvitationNotification;
use App\Notifications\NewMemberWelcomeNotification;
use App\Notifications\BandOwnershipInvitationNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

class UserInvitationService
{

    /**
     * Invite a new user to join the CMC.
     */
    public function inviteUser(string $email, array $data = []): Invitation
    {
        return DB::transaction(function () use ($email, $data) {
            // Generate invitation token
            $invitation = $this->generateInvitation($email, $data);

            // Send invitation notification
            Notification::route('mail', $email)
                ->notify(new UserInvitationNotification($invitation, $data));

            $invitation->markAsSent();

            return $invitation;
        });
    }

    /**
     * Resend an invitation to an email address.
     */
    public function resendInvitation(string $email): Invitation
    {
        $invitations = Invitation::withoutGlobalScopes()->forEmail($email)->get();

        if ($invitations->some(fn($inv) => $inv->isUsed())) {
            throw new \Exception('User has already accepted invitation.');
        }

        $invitations = $invitations->filter(fn($inv) => !$inv->isUsed());
        if ($invitations->isEmpty()) {
            throw new \Exception('No invitations found for this email.');
        }

        $lastInvite = $invitations->last();

        // Delete existing invitations to avoid unique constraint violation
        $invitations->each(fn($inv) => $inv->delete());

        $newInvite = Invitation::create([
            'email' => $email,
            'message' => $lastInvite->message,
            'inviter_id' => Auth::user()?->id,
        ]);

        // Send invitation notification
        Notification::route('mail', $email)
            ->notify(new UserInvitationNotification($newInvite, ['message' => $newInvite->message]));

        $newInvite->markAsSent();

        return $newInvite;
    }

    /**
     * Accept an invitation using a token.
     */
    public function acceptInvitation(string $token, array $userData): ?User
    {
        $invite = Invitation::withoutGlobalScopes()->where('token', $token)->first();

        if (!$invite) {
            throw new \Exception('Invitation not found.');
        }

        if ($invite->isExpired()) {
            throw new \Exception('Invitation has expired.');
        }

        if ($invite->isUsed()) {
            throw new \Exception('Invitation has already been used.');
        }

        return DB::transaction(function () use ($invite, $userData) {
            // Check if user already exists
            $user = User::where('email', $invite->email)->first();

            if ($user) {
                // User exists - just mark invitation as used and update if needed

                if (!$user->email_verified_at) {
                    $user->update(['email_verified_at' => now()]);
                }
            } else {
                // Create new user
                $user = User::create([
                    'name' => $userData['name'],
                    'email' => $invite->email,
                    'password' => bcrypt($userData['password']),
                    'email_verified_at' => now(),
                ]);
            }
            $invite->markAsUsed();

            // Confirm any pending band ownerships
            $this->confirmBandOwnership($user, $invite);

            // Send welcome notification for new members
            $user->notify(new NewMemberWelcomeNotification($user));

            return $user;
        });
    }

    /**
     * Generate a signed invitation token for a user.
     */
    public function generateInvitation(string $email, array $data = []): Invitation
    {
        return Invitation::create([
            'inviter_id' => Auth::user()?->id,
            'email' => $email,
            'expires_at' => now()->addWeeks(1),
            'message' => $data['message'] ?? 'Join me at Corvallis Music Collective!',
            'data' => $data,
        ]);
    }

    /**
     * Find invitation by token.
     */
    public function findInvitationByToken(string $token): ?Invitation
    {
        return Invitation::withoutGlobalScopes()->where('token', $token)->first();
    }


    /**
     * Get all pending invitations.
     */
    public function getPendingInvitations(): \Illuminate\Database\Eloquent\Collection
    {
        return Invitation::withoutGlobalScopes()
            ->whereNull('used_at')
            ->with('inviter')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Cancel a pending invitation.
     */
    public function cancelInvitation(Invitation $invitation): bool
    {
        // Only cancel if invitation hasn't been used
        if ($invitation->isUsed()) {
            return false;
        }

        return $invitation->delete();
    }

    /**
     * Get invitation statistics.
     */
    public function getInvitationStats(): array
    {
        $totalInvitations = Invitation::withoutGlobalScopes()->count();
        $pendingInvitations = Invitation::withoutGlobalScopes()->whereNull('used_at')->count();
        $acceptedInvitations = Invitation::withoutGlobalScopes()->whereNotNull('used_at')->count();
        $expiredInvitations = Invitation::withoutGlobalScopes()
            ->whereNull('used_at')
            ->where('expires_at', '<', now())
            ->count();

        return [
            'total_invitations' => $totalInvitations,
            'pending_invitations' => $pendingInvitations,
            'accepted_invitations' => $acceptedInvitations,
            'expired_invitations' => $expiredInvitations,
            'acceptance_rate' => $totalInvitations > 0 ? ($acceptedInvitations / $totalInvitations) * 100 : 0,
            'pending_active' => $pendingInvitations - $expiredInvitations,
        ];
    }

    /**
     * Invite a user to join CMC and create/own a band.
     *
     * @param string $email
     * @param string $bandName
     * @param array $bandData Additional band data (genre, description, etc.)
     * @param array $roleNames User roles to assign
     * @return array ['invitation' => Invitation, 'band' => Band, 'user' => User|null]
     */
    public function inviteUserWithBand(string $email, string $bandName, array $bandData = []): Invitation
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
            $invitation = $this->generateInvitation($email, [
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

    /**
     * Confirm band ownership when user completes their invitation.
     */
    public function confirmBandOwnership(User $user, Invitation $invitation): void
    {
        if (!isset($invitation->data['band_id'])) {
            return;
        }

        // Use BandService to handle band ownership confirmation
        BandService::confirmBandOwnershipFromInvitation($user, $invitation->data);

        // Assign any roles specified in the invitation
        if (!empty($invitation->data['roles'])) {
            $roles = Role::whereIn('name', $invitation->data['roles'])->get();
            $user->syncRoles($roles);
        }
    }
}
