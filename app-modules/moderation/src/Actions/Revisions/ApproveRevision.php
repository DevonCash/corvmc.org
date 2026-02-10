<?php

namespace CorvMC\Moderation\Actions\Revisions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

use App\Models\User;

use CorvMC\Moderation\Events\RevisionApproved as RevisionApprovedEvent;
use CorvMC\Moderation\Models\Revision;
use CorvMC\Moderation\Notifications\RevisionApprovedNotification;

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

        $result = DB::transaction(function () use ($revision, $reviewer, $reason) {
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

            return true;
        });

        RevisionApprovedEvent::dispatch($revision, $reviewer);

        // Send notification outside transaction
        try {
            $revision->submittedBy->notify(new RevisionApprovedNotification($revision));
        } catch (\Exception $e) {
            Log::error('Failed to send revision approved notification', [
                'revision_id' => $revision->id,
                'submitter_id' => $revision->submitted_by_id,
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }
}
