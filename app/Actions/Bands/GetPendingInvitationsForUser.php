<?php

namespace App\Actions\Bands;

use App\Models\Band;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class GetPendingInvitationsForUser
{
    use AsAction;

    /**
     * Get all pending invitations for a user.
     */
    public function handle(User $user): Collection
    {
        return Band::whereHas('members', function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->where('status', 'invited');
        })->with(['members' => function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->where('status', 'invited')
                ->withPivot('role', 'position', 'invited_at');
        }])->get();
    }
}
