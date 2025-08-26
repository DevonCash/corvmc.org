<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\UserInvitationNotification;
use App\Notifications\NewMemberWelcomeNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UserInvitationService
{
    /**
     * Invite a new user to join the CMC.
     */
    public function inviteUser(string $email, array $roleNames = []): User
    {
        return DB::transaction(function () use ($email, $roleNames) {
            // Create the user with temporary data
            $user = User::create([
                'email' => $email,
                'name' => 'Invited User',
                'password' => bcrypt(Str::random(32)), // Temporary password
            ]);

            // Assign roles if provided
            if (!empty($roleNames)) {
                $roles = Role::whereIn('name', $roleNames)->get();
                $user->syncRoles($roles);
            }

            // Generate invitation token
            $token = $this->generateInvitationToken($user);

            // Send invitation notification
            $user->notify(new UserInvitationNotification($user, $token, $roleNames));

            return $user;
        });
    }

    /**
     * Resend an invitation to an existing user.
     */
    public function resendInvitation(User $user): bool
    {
        // Only resend if user hasn't completed their profile
        if ($user->email_verified_at !== null) {
            return false;
        }

        $token = $this->generateInvitationToken($user);
        $roleNames = $user->roles->pluck('name')->toArray();

        $user->notify(new UserInvitationNotification($user, $token, $roleNames));

        return true;
    }

    /**
     * Accept an invitation using a token.
     */
    public function acceptInvitation(string $token, array $userData): ?User
    {
        $user = $this->findUserByToken($token);
        
        if (!$user || $this->isTokenExpired($token)) {
            return null;
        }

        return DB::transaction(function () use ($user, $userData) {
            $user->update([
                'name' => $userData['name'],
                'password' => bcrypt($userData['password']),
                'email_verified_at' => now(),
            ]);

            // Clear the invitation token
            $this->clearInvitationToken($user);

            // Send welcome notification for new members
            $user->notify(new NewMemberWelcomeNotification($user));

            return $user;
        });
    }

    /**
     * Generate a signed invitation token for a user.
     */
    public function generateInvitationToken(User $user): string
    {
        return encrypt([
            'user_id' => $user->id,
            'email' => $user->email,
            'expires_at' => now()->addWeek()->timestamp,
        ]);
    }

    /**
     * Find user by invitation token.
     */
    public function findUserByToken(string $token): ?User
    {
        try {
            $data = decrypt($token);
            return User::where('id', $data['user_id'])
                ->where('email', $data['email'])
                ->first();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if invitation token is expired.
     */
    public function isTokenExpired(string $token): bool
    {
        try {
            $data = decrypt($token);
            return now()->timestamp > $data['expires_at'];
        } catch (\Exception $e) {
            return true;
        }
    }

    /**
     * Clear invitation token (mark as used).
     */
    protected function clearInvitationToken(User $user): void
    {
        // In a more complex system, you might store tokens in database
        // For now, we just mark email as verified which invalidates the invitation
    }

    /**
     * Get all pending invitations (unverified users).
     */
    public function getPendingInvitations(): \Illuminate\Database\Eloquent\Collection
    {
        return User::whereNull('email_verified_at')
            ->where('name', 'Invited User')
            ->with('roles')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Cancel a pending invitation.
     */
    public function cancelInvitation(User $user): bool
    {
        // Only cancel if invitation is still pending
        if ($user->email_verified_at !== null) {
            return false;
        }

        return $user->delete();
    }

    /**
     * Get invitation statistics.
     */
    public function getInvitationStats(): array
    {
        $pending = $this->getPendingInvitations();
        $completed = User::whereNotNull('email_verified_at')->count();
        
        return [
            'pending_invitations' => $pending->count(),
            'completed_registrations' => $completed,
            'total_users' => $pending->count() + $completed,
            'acceptance_rate' => $completed > 0 ? ($completed / ($pending->count() + $completed)) * 100 : 0,
            'pending_by_role' => $pending->groupBy(function ($user) {
                return $user->roles->pluck('name')->implode(', ') ?: 'member';
            })->map->count(),
        ];
    }
}