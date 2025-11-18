<?php

namespace App\Actions\Trust;

use App\Enums\TrustLevel;
use App\Models\User;
use App\Models\UserTrustBalance;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class GetUsersByTrustLevel
{
    use AsAction;

    /**
     * Get users by trust level for queries/admin.
     */
    public function handle(TrustLevel $level, string $contentType = 'global'): Collection
    {
        $minPoints = $level->getThreshold();
        $nextLevel = $level->getNextLevel();
        $maxPoints = $nextLevel?->getThreshold() - 1;

        $query = UserTrustBalance::where('content_type', $contentType)
            ->where('balance', '>=', $minPoints);

        if ($maxPoints !== null) {
            $query->where('balance', '<=', $maxPoints);
        }

        return User::whereIn('id', $query->pluck('user_id'))
            ->get();
    }
}
