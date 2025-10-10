<?php

namespace App\Actions\Bands;

use App\Models\Band;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ConfirmBandOwnershipFromInvitation
{
    use AsAction;

    /**
     * Confirm band ownership from invitation data.
     */
    public function handle(User $user, array $invitationData): void
    {
        if (!isset($invitationData['band_id'])) {
            throw new \InvalidArgumentException('Invitation data does not contain band_id.');
        }

        $band = Band::withoutGlobalScopes()->findOrFail($invitationData['band_id']);

        if ($band->status !== 'pending_owner_verification') {
            throw new \InvalidArgumentException('Band is not pending owner verification.');
        }

        DB::transaction(function () use ($band, $user) {
            // Add user as band member with owner role
            $band->members()->attach($user->id, [
                'role' => 'owner',
                'status' => 'active',
            ]);

            $band->update([
                'status' => 'active',
                'owner_id' => $user->id
            ]);
        });
    }
}
