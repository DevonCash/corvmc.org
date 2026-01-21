<?php

namespace CorvMC\Moderation\Actions\Trust;

use App\Models\User;
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

        // CommunityEvent was removed - now handled by Event model
        // Add logic for other content types as needed

        return $totalPoints;
    }
}
