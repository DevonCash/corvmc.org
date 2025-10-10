<?php

namespace App\Actions\Users;

use App\Models\User;
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
