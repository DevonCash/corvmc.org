<?php

namespace App\Actions\Invitations;

use App\Models\Invitation;
use Illuminate\Support\Facades\Auth;
use Lorisleiva\Actions\Concerns\AsAction;

class GenerateInvitation
{
    use AsAction;

    /**
     * Generate a signed invitation token for a user.
     */
    public function handle(string $email, array $data = []): Invitation
    {
        return Invitation::create([
            'inviter_id' => Auth::user()?->id,
            'email' => $email,
            'expires_at' => now()->addWeeks(1),
            'message' => $data['message'] ?? 'Join me at Corvallis Music Collective!',
            'data' => $data,
        ]);
    }
}
