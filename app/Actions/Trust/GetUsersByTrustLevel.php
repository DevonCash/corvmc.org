<?php

namespace App\Actions\Trust;

use App\Models\User;
use App\Models\UserTrustBalance;
use App\Support\TrustConstants;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class GetUsersByTrustLevel
{
    use AsAction;

    /**
     * Get users by trust level for queries/admin.
     */
    public function handle(string $level, string $contentType = 'global'): Collection
    {
        $minPoints = match($level) {
            'auto-approved' => TrustConstants::TRUST_AUTO_APPROVED,
            'verified' => TrustConstants::TRUST_VERIFIED,
            'trusted' => TrustConstants::TRUST_TRUSTED,
            default => 0
        };

        $maxPoints = match($level) {
            'verified' => TrustConstants::TRUST_AUTO_APPROVED - 1,
            'trusted' => TrustConstants::TRUST_VERIFIED - 1,
            'pending' => TrustConstants::TRUST_TRUSTED - 1,
            default => null
        };

        $query = UserTrustBalance::where('content_type', $contentType)
            ->where('balance', '>=', $minPoints);

        if ($maxPoints !== null) {
            $query->where('balance', '<=', $maxPoints);
        }

        return User::whereIn('id', $query->pluck('user_id'))
            ->get();
    }
}
