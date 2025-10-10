<?php

namespace App\Actions\Trust;

use App\Models\User;
use App\Support\TrustConstants;
use Lorisleiva\Actions\Concerns\AsAction;

class GetTrustLevelInfo
{
    use AsAction;

    /**
     * Get trust level display information.
     */
    public function handle(User $user, string $contentType = 'global'): array
    {
        $points = GetTrustBalance::run($user, $contentType);
        $level = GetTrustLevel::run($user, $contentType);

        $info = [
            'level' => $level,
            'points' => $points,
            'content_type' => $contentType,
            'can_auto_approve' => CanAutoApprove::run($user, $contentType),
            'fast_track' => $points >= TrustConstants::TRUST_TRUSTED,
        ];

        // Add progress to next level
        switch ($level) {
            case 'pending':
                $info['next_level'] = 'trusted';
                $info['points_needed'] = TrustConstants::TRUST_TRUSTED - $points;
                break;
            case 'trusted':
                $info['next_level'] = 'verified';
                $info['points_needed'] = TrustConstants::TRUST_VERIFIED - $points;
                break;
            case 'verified':
                $info['next_level'] = 'auto-approved';
                $info['points_needed'] = TrustConstants::TRUST_AUTO_APPROVED - $points;
                break;
            case 'auto-approved':
                $info['next_level'] = null;
                $info['points_needed'] = 0;
                break;
        }

        return $info;
    }
}
