<?php

namespace App\Actions\Revisions;

use App\Models\Revision;
use App\Notifications\RevisionApprovedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class AutoApproveRevision
{
    use AsAction;

    /**
     * Auto-approve a revision based on trust level.
     */
    public function handle(Revision $revision): bool
    {
        Log::info('Auto-approving revision based on trust level', [
            'revision_id' => $revision->id,
            'submitted_by' => $revision->submitted_by_id,
        ]);

        $result = DB::transaction(function () use ($revision) {
            // Mark as auto-approved
            $revision->update([
                'status' => Revision::STATUS_APPROVED,
                'auto_approved' => true,
                'reviewed_at' => now(),
                'review_reason' => 'Auto-approved based on user trust level',
            ]);

            // Apply the changes to the model
            ApplyRevision::run($revision);

            // Award trust points for successful content
            AwardTrustPointsForRevision::run($revision);

            return true;
        });

        // Send notification outside transaction
        try {
            $revision->submittedBy->notify(new RevisionApprovedNotification($revision));
        } catch (\Exception $e) {
            \Log::error('Failed to send auto-approved revision notification', [
                'revision_id' => $revision->id,
                'submitter_id' => $revision->submitted_by_id,
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }
}
