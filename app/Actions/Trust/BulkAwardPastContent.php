<?php

namespace App\Actions\Trust;

use App\Models\User;
use App\Support\TrustConstants;
use Lorisleiva\Actions\Concerns\AsAction;

class BulkAwardPastContent
{
    use AsAction;

    /**
     * Bulk award points for past successful content (migration/backfill).
     */
    public function handle(User $user, string $contentType = 'global'): int
    {
        $totalPoints = 0;

        if ($contentType === 'App\\Models\\CommunityEvent' || $contentType === 'global') {
            $successfulEvents = \App\Models\CommunityEvent::where('organizer_id', $user->id)
                ->where('status', \App\Models\CommunityEvent::STATUS_APPROVED)
                ->where('start_time', '<', now())
                ->whereDoesntHave('reports', function ($query) {
                    $query->where('status', 'upheld');
                })
                ->count();

            if ($successfulEvents > 0) {
                $points = $successfulEvents * TrustConstants::POINTS_SUCCESSFUL_CONTENT;
                AwardTrustPoints::run(
                    $user,
                    $points,
                    'App\\Models\\CommunityEvent',
                    'bulk_award',
                    null,
                    "Bulk award for {$successfulEvents} successful events"
                );
                $totalPoints += $points;
            }
        }

        // Add similar logic for other content types as needed

        return $totalPoints;
    }
}
