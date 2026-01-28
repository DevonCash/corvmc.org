<?php

namespace CorvMC\Moderation\Actions\Trust;

use App\Models\User;
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
        $points = match ($violationType) {
            'spam' => config('moderation.points.spam_violation'),
            'major' => config('moderation.points.major_violation'),
            'minor' => config('moderation.points.minor_violation'),
            default => config('moderation.points.minor_violation'),
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
            'new_total' => $user->getTrustBalance($contentType),
        ]);
    }
}
