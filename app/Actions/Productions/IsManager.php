<?php

namespace App\Actions\Productions;

use App\Models\Production;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class IsManager
{
    use AsAction;

    /**
     * Check if a user is the manager of a production.
     */
    public function handle(Production $production, User $user): bool
    {
        return $production->manager_id === $user->id;
    }
}
