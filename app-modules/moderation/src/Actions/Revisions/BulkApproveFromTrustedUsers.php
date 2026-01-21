<?php

namespace CorvMC\Moderation\Actions\Revisions;

use CorvMC\Moderation\Models\Revision;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class BulkApproveFromTrustedUsers
{
    use AsAction;

    /**
     * Bulk approve revisions from trusted users.
     */
    public function handle(User $reviewer): int
    {
        $trustedRevisions = Revision::pending()
            ->whereHas('submittedBy', function ($query) {
                // Get revisions from users with fast-track approval
                $query->whereRaw("JSON_EXTRACT(trust_points, '$.global') >= ?", [
                    config('moderation.thresholds.trusted'),
                ]);
            })
            ->get();

        $approved = 0;
        foreach ($trustedRevisions as $revision) {
            try {
                ApproveRevision::run($revision, $reviewer, 'Bulk approved - trusted user');
                $approved++;
            } catch (\Exception $e) {
                Log::error('Failed to bulk approve revision', [
                    'revision_id' => $revision->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $approved;
    }
}
