<?php

namespace CorvMC\Moderation\Actions\Trust;

use CorvMC\Moderation\Models\TrustTransaction;
use CorvMC\Moderation\Models\UserTrustBalance;
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
            'auto_approved_users' => (clone $query)->where('balance', '>=', config('moderation.thresholds.auto_approved'))->count(),
            'verified_users' => (clone $query)->whereBetween('balance', [config('moderation.thresholds.verified'), config('moderation.thresholds.auto_approved') - 1])->count(),
            'trusted_users' => (clone $query)->whereBetween('balance', [config('moderation.thresholds.trusted'), config('moderation.thresholds.verified') - 1])->count(),
            'pending_users' => (clone $query)->where('balance', '<', config('moderation.thresholds.trusted'))->count(),
            'average_trust' => round($query->avg('balance') ?? 0, 2),
            'total_points_awarded' => TrustTransaction::where('points', '>', 0)->sum('points'),
            'total_points_deducted' => abs(TrustTransaction::where('points', '<', 0)->sum('points')),
        ];
    }
}
