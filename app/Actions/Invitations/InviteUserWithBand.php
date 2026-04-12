<?php

namespace App\Actions\Invitations;

use App\Models\Invitation;
use App\Services\InvitationService;
use CorvMC\Bands\Models\Band;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use InvitationService::inviteUserWithBand() instead
 * This action is maintained for backward compatibility only.
 * New code should use the InvitationService directly.
 */
class InviteUserWithBand
{
    use AsAction;

    /**
     * @deprecated Use InvitationService::inviteUserWithBand() instead
     */
    public function handle(string $email, string $bandName, array $bandData = []): Invitation
    {
        // For backward compatibility, if bandName is provided without a Band model,
        // we'll fall back to the old InviteUser behavior
        $data = [
            'band_name' => $bandName,
            'band_data' => $bandData,
        ];
        
        return app(InvitationService::class)->inviteUser($email, $data);
    }
}
