<?php

namespace App\Actions\Bands;

use App\Models\Band;
use App\Models\User;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateUserAndInviteToBand
{
    use AsAction;

    /**
     * Create a new CMC user by email and invite them to the band.
     */
    public function handle(
        Band $band,
        string $name,
        string $email,
        string $role = 'member',
        ?string $position = null
    ): User {
        // Create the user with temporary password
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt(Str::random(32)),
        ]);

        // Send band invitation
        InviteMember::run(
            band: $band,
            user: $user,
            role: $role,
            position: $position,
            displayName: $name
        );

        return $user;
    }
}
