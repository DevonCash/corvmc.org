<?php

namespace App\Actions\Invitations;

use App\Models\Invitation;
use Lorisleiva\Actions\Concerns\AsAction;

class GetPendingInvitations
{
    use AsAction;

    /**
     * Get all pending invitations.
     */
    public function handle(): \Illuminate\Database\Eloquent\Collection
    {
        return Invitation::withoutGlobalScopes()
            ->whereNull('used_at')
            ->with('inviter')
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
