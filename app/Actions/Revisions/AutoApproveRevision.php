<?php

namespace App\Actions\Revisions;

use App\Models\Revision;
use App\Notifications\RevisionApprovedNotification;
use App\Services\TrustService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class AutoApproveRevision
{
    use AsAction;

    public function __construct(
        protected TrustService $trustService
    ) {}

    /**
     * Auto-approve a revision based on trust level.
     */
    public function handle(Revision $revision): bool
    {
        Log::info('Auto-approving revision based on trust level', [
            'revision_id' => $revision->id,
            'submitted_by' => $revision->submitted_by_id,
        ]);

        return DB::transaction(function () use ($revision) {
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

            // Send notification
            $revision->submittedBy->notify(new RevisionApprovedNotification($revision));

            return true;
        });
    }
}
