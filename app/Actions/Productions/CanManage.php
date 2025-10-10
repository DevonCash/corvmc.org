<?php

namespace App\Actions\Productions;

use App\Models\Production;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class CanManage
{
    use AsAction;

    /**
     * Check if a user can manage a production (is manager or admin).
     */
    public function handle(Production $production, User $user): bool
    {
        return IsManager::run($production, $user) || $user->can('manage productions');
    }
}
