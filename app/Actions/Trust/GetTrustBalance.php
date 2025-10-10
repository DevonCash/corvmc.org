<?php

namespace App\Actions\Trust;

use App\Models\User;
use App\Models\UserTrustBalance;
use Lorisleiva\Actions\Concerns\AsAction;

class GetTrustBalance
{
    use AsAction;

    /**
     * Get user's current trust balance.
     */
    public function handle(User $user, string $contentType = 'global'): int
    {
        return UserTrustBalance::where('user_id', $user->id)
            ->where('content_type', $contentType)
            ->value('balance') ?? 0;
    }
}
