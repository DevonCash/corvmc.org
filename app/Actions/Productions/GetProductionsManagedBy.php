<?php

namespace App\Actions\Productions;

use App\Models\Production;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class GetProductionsManagedBy
{
    use AsAction;

    /**
     * Get productions managed by a user.
     */
    public function handle(User $user): Collection
    {
        return Production::where('manager_id', $user->id)
            ->orderBy('start_time', 'desc')
            ->get();
    }
}
