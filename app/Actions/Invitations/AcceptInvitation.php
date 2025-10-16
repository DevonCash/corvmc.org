<?php

namespace App\Actions\Invitations;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Lorisleiva\Actions\Concerns\AsAction;

class AcceptInvitation
{
    use AsAction;

    /**
     * Accept an invitation and create the user account.
     */
    public function handle(string $token, array $userData): ?User
    {
        $invitation = FindInvitationByToken::run($token);

        if (!$invitation || $invitation->isExpired() || $invitation->isUsed()) {
            return null;
        }

        return DB::transaction(function () use ($invitation, $userData) {

            // Create the user
            $user = User::create([
                'name' => $userData['name'],
                'email' => $invitation->email,
                'password' => Hash::make($userData['password']),
                'email_verified_at' => now(),
            ]);

            // Mark invitation as used
            $invitation->markAsUsed();

            return $user;
        });
    }
}
