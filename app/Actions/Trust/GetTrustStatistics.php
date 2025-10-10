<?php

namespace App\Actions\Trust;

use App\Models\TrustTransaction;
use App\Models\UserTrustBalance;
use App\Support\TrustConstants;
use Lorisleiva\Actions\Concerns\AsAction;

class GetTrustStatistics
{
    use AsAction;

    /**
     * Get trust statistics for reporting.
     */
    public function handle(?string $contentType = null): array
    {
        $query = UserTrustBalance::query();

        if ($contentType) {
            $query->where('content_type', $contentType);
        }

        return [
            'total_users' => $query->distinct('user_id')->count(),
            'auto_approved_users' => (clone $query)->where('balance', '>=', TrustConstants::TRUST_AUTO_APPROVED)->count(),
            'verified_users' => (clone $query)->whereBetween('balance', [TrustConstants::TRUST_VERIFIED, TrustConstants::TRUST_AUTO_APPROVED - 1])->count(),
            'trusted_users' => (clone $query)->whereBetween('balance', [TrustConstants::TRUST_TRUSTED, TrustConstants::TRUST_VERIFIED - 1])->count(),
            'pending_users' => (clone $query)->where('balance', '<', TrustConstants::TRUST_TRUSTED)->count(),
            'average_trust' => round($query->avg('balance') ?? 0, 2),
            'total_points_awarded' => TrustTransaction::where('points', '>', 0)->sum('points'),
            'total_points_deducted' => abs(TrustTransaction::where('points', '<', 0)->sum('points')),
        ];
    }
}
