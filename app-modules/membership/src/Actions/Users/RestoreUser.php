<?php

namespace CorvMC\Membership\Actions\Users;

use CorvMC\Membership\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class RestoreUser
{
    use AsAction;

    /**
     * Restore a soft-deleted user.
     */
    public function handle(User $user): bool
    {
        return $user->restore();
    }
}
