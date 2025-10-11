<?php

namespace App\Actions\Bands;

use App\Models\Band;
use App\Models\BandMember;
use App\Models\User;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateBandMember
{
    use AsAction;

    /**
     * Update a band member with flexible user/non-user handling.
     */
    public function handle(
        BandMember $member,
        Band $band,
        array $data
    ): BandMember {
        $updateData = [
            'role' => $data['role'] ?? $member->role,
            'position' => $data['position'] ?? null,
            'name' => $data['name'],
        ];

        if (isset($data['user_id']) && $data['user_id']) {
            // Existing CMC member selected
            $updateData['user_id'] = $data['user_id'];

            // If changing from non-CMC to CMC, convert to invitation
            if (!$member->user_id && $member->status === 'active') {
                $updateData['status'] = 'invited';
                $updateData['invited_at'] = now();

                $user = User::find($data['user_id']);
                if ($user) {
                    ResendInvitation::run($band, $user);
                }
            }
        } elseif (isset($data['email']) && $data['email']) {
            // Email invitation - create new user
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => bcrypt(Str::random(32)),
            ]);

            $updateData['user_id'] = $user->id;
            $updateData['status'] = 'invited';
            $updateData['invited_at'] = now();

            ResendInvitation::run($band, $user);
        } else {
            // No user association - keep as non-CMC member
            $updateData['user_id'] = null;
            // If converting from CMC to non-CMC, make them active
            if ($member->user_id) {
                $updateData['status'] = 'active';
                $updateData['invited_at'] = null;
            }
        }

        $member->update($updateData);

        return $member->fresh();
    }
}
