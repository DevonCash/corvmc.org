<?php

namespace App\Actions\Revisions;

use App\Models\Revision;
use Lorisleiva\Actions\Concerns\AsAction;

class CleanupOldRevisions
{
    use AsAction;

    /**
     * Clean up old rejected revisions.
     */
    public function handle(int $daysOld = 90): int
    {
        return Revision::where('status', Revision::STATUS_REJECTED)
            ->where('created_at', '<', now()->subDays($daysOld))
            ->delete();
    }
}
