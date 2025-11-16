<?php

namespace App\Actions\Revisions;

use App\Models\Revision;
use App\Models\User;
use App\Notifications\RevisionApprovedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class ApproveRevision
{
    use AsAction;

    /**
     * Approve a revision manually.
     */
    public function handle(Revision $revision, User $reviewer, ?string $reason = null): bool
    {
        if (! $revision->isPending()) {
            throw new \InvalidArgumentException('Revision is not pending approval');
        }

        Log::info('Manually approving revision', [
            'revision_id' => $revision->id,
            'reviewed_by' => $reviewer->id,
            'reason' => $reason,
        ]);

        return DB::transaction(function () use ($revision, $reviewer, $reason) {
            // Update revision status
            $revision->update([
                'status' => Revision::STATUS_APPROVED,
                'reviewed_by_id' => $reviewer->id,
                'reviewed_at' => now(),
                'review_reason' => $reason ?? 'Approved by moderator',
            ]);

            // Apply the changes to the model
            ApplyRevision::run($revision);

            // Award trust points
            AwardTrustPointsForRevision::run($revision);

            // Send notification
            $revision->submittedBy->notify(new RevisionApprovedNotification($revision));

            return true;
        });
    }
}
