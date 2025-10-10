<?php

namespace App\Actions\Productions;

use App\Models\Production;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class TransferManagement
{
    use AsAction;

    /**
     * Transfer production management to another user.
     */
    public function handle(Production $production, User $newManager): bool
    {
        $production->update([
            'manager_id' => $newManager->id,
        ]);

        return true;
    }
}
