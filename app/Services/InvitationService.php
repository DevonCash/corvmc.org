<?php

namespace App\Services;

use App\Models\Invitation;
use App\Models\User;
use CorvMC\Bands\Models\Band;
use CorvMC\Membership\Notifications\UserInvitationNotification;
use CorvMC\Membership\Notifications\BandInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Service for managing user invitations.
 * 
 * This service handles all invitation-related operations including
 * generation, acceptance, cancellation, and band-specific invitations.
 */
class InvitationService
{
    /**
     * Generate an invitation token and create the invitation record.
     */
    public function generate(string $email, array $data = []): Invitation
    {
        $token = Str::random(64);
        
        return Invitation::create([
            'inviter_id' => auth()->id(),
            'email' => $email,
            'token' => $token,
            'expires_at' => now()->addWeeks(1),
            'message' => $data['message'] ?? 'Join me at Corvallis Music Collective!',
            'data' => $data,
        ]);
    }

    /**
     * Send an invitation to a user.
     */
    public function inviteUser(string $email, array $data = []): Invitation
    {
        return DB::transaction(function () use ($email, $data) {
            $invitation = $this->generate($email, $data);

            Notification::route('mail', $email)
                ->notify(new UserInvitationNotification($invitation, $data));

            $invitation->update(['last_sent_at' => now()]);

            return $invitation;
        });
    }

    /**
     * Invite a user with a band association.
     */
    public function inviteUserWithBand(string $email, Band $band, array $data = []): Invitation
    {
        return DB::transaction(function () use ($email, $band, $data) {
            $data['band_id'] = $band->id;
            $data['band_name'] = $band->name;
            
            $invitation = $this->generate($email, $data);

            Notification::route('mail', $email)
                ->notify(new BandInvitationNotification($invitation, $band, $data));

            $invitation->update(['last_sent_at' => now()]);

            return $invitation;
        });
    }

    /**
     * Accept an invitation and create/update the user account.
     */
    public function accept(Invitation $invitation, array $userData = []): User
    {
        if ($invitation->expires_at->isPast()) {
            throw new \Exception('This invitation has expired.');
        }

        if ($invitation->used_at) {
            throw new \Exception('This invitation has already been used.');
        }

        return DB::transaction(function () use ($invitation, $userData) {
            // Check if user already exists
            $user = User::where('email', $invitation->email)->first();

            if (!$user) {
                // Create new user
                $user = User::create([
                    'name' => $userData['name'] ?? explode('@', $invitation->email)[0],
                    'email' => $invitation->email,
                    'password' => bcrypt($userData['password'] ?? Str::random(16)),
                    'email_verified_at' => now(),
                ]);
            }

            // Assign role if specified in invitation data
            if (isset($invitation->data['role'])) {
                $user->assignRole($invitation->data['role']);
            }

            // Handle band membership if applicable
            if (isset($invitation->data['band_id']) && $band = Band::find($invitation->data['band_id'])) {
                $band->members()->attach($user->id, [
                    'role' => $invitation->data['band_role'] ?? 'member',
                    'status' => 'active',
                    'joined_at' => now(),
                ]);
            }

            // Mark invitation as used
            $invitation->update([
                'used_at' => now(),
            ]);

            return $user;
        });
    }

    /**
     * Find an invitation by its token.
     */
    public function findByToken(string $token): ?Invitation
    {
        return Invitation::whereNull('used_at')
            ->where('expires_at', '>', now())
            ->where('token', $token)
            ->first();
    }

    /**
     * Cancel an invitation.
     */
    public function cancel(Invitation $invitation): bool
    {
        if ($invitation->used_at) {
            throw new \Exception('Cannot cancel a used invitation.');
        }

        return $invitation->delete();
    }

    /**
     * Resend an invitation email.
     */
    public function resend(Invitation $invitation): Invitation
    {
        if ($invitation->used_at) {
            throw new \Exception('Cannot resend a used invitation.');
        }

        if ($invitation->expires_at->isPast()) {
            // Extend expiration date when resending
            $invitation->update(['expires_at' => now()->addWeeks(1)]);
        }

        // Send appropriate notification based on invitation type
        if (isset($invitation->data['band_id']) && $band = Band::find($invitation->data['band_id'])) {
            Notification::route('mail', $invitation->email)
                ->notify(new BandInvitationNotification($invitation, $band, $invitation->data ?? []));
        } else {
            Notification::route('mail', $invitation->email)
                ->notify(new UserInvitationNotification($invitation, $invitation->data ?? []));
        }

        $invitation->update(['last_sent_at' => now()]);

        return $invitation;
    }

    /**
     * Confirm band ownership through invitation process.
     */
    public function confirmBandOwnership(User $user, Band $band): bool
    {
        // Update band ownership
        $band->update(['owner_id' => $user->id]);

        // Update band member role to owner
        $band->members()->updateExistingPivot($user->id, [
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        return true;
    }

    /**
     * Get statistics about invitations.
     */
    public function getStats(): array
    {
        return [
            'total' => Invitation::count(),
            'pending' => Invitation::whereNull('used_at')->where('expires_at', '>', now())->count(),
            'accepted' => Invitation::whereNotNull('used_at')->count(),
            'expired' => Invitation::where('expires_at', '<', now())->whereNull('used_at')->count(),
            'sent_today' => Invitation::whereDate('created_at', today())->count(),
            'acceptance_rate' => $this->calculateAcceptanceRate(),
        ];
    }

    /**
     * Calculate the invitation acceptance rate.
     */
    protected function calculateAcceptanceRate(): float
    {
        $total = Invitation::where('created_at', '<', now()->subDay())->count();
        
        if ($total === 0) {
            return 0.0;
        }

        $accepted = Invitation::whereNotNull('used_at')
            ->where('created_at', '<', now()->subDay())
            ->count();

        return round(($accepted / $total) * 100, 1);
    }

    /**
     * Clean up expired invitations.
     */
    public function cleanupExpired(): int
    {
        return Invitation::where('expires_at', '<', now()->subDays(30))
            ->whereNull('used_at')
            ->delete();
    }
}