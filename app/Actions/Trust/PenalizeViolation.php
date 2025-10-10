<?php

namespace App\Actions\Trust;

use App\Models\User;
use App\Support\TrustConstants;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class PenalizeViolation
{
    use AsAction;

    /**
     * Penalize user for violation.
     */
    public function handle(
        User $user,
        string $violationType,
        string $contentType = 'global',
        ?int $sourceId = null,
        string $reason = '',
        ?User $penalizedBy = null
    ): void {
        $points = match($violationType) {
            'spam' => TrustConstants::POINTS_SPAM_VIOLATION,
            'major' => TrustConstants::POINTS_MAJOR_VIOLATION,
            'minor' => TrustConstants::POINTS_MINOR_VIOLATION,
            default => TrustConstants::POINTS_MINOR_VIOLATION,
        };

        AwardTrustPoints::run(
            $user,
            $points,
            $contentType,
            "{$violationType}_violation",
            $sourceId,
            "Violation: {$violationType} - {$reason}",
            $penalizedBy
        );

        Log::warning('Trust points deducted for violation', [
            'user_id' => $user->id,
            'content_type' => $contentType,
            'violation_type' => $violationType,
            'points_deducted' => abs($points),
            'reason' => $reason,
            'new_total' => GetTrustBalance::run($user, $contentType)
        ]);
    }
}
