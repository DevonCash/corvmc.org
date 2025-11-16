<?php

namespace App\Actions\Revisions;

use App\Models\Revision;
use App\Models\User;
use App\Notifications\RevisionRejectedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class RejectRevision
{
    use AsAction;

    /**
     * Reject a revision.
     */
    public function handle(Revision $revision, User $reviewer, string $reason): bool
    {
        if (! $revision->isPending()) {
            throw new \InvalidArgumentException('Revision is not pending approval');
        }

        Log::info('Rejecting revision', [
            'revision_id' => $revision->id,
            'reviewed_by' => $reviewer->id,
            'reason' => $reason,
        ]);

        return DB::transaction(function () use ($revision, $reviewer, $reason) {
            // Update revision status
            $revision->update([
                'status' => Revision::STATUS_REJECTED,
                'reviewed_by_id' => $reviewer->id,
                'reviewed_at' => now(),
                'review_reason' => $reason,
            ]);

            // Penalize submitter trust if this was a problematic revision
            $this->handleRejectionPenalty($revision, $reason);

            // Send notification
            $revision->submittedBy->notify(new RevisionRejectedNotification($revision));

            return true;
        });
    }

    /**
     * Handle trust penalty for rejected revisions.
     */
    protected function handleRejectionPenalty(Revision $revision, string $reason): void
    {
        $submitter = $revision->submittedBy;
        $contentType = $revision->revisionable ? get_class($revision->revisionable) : null;

        // Determine violation type based on rejection reason
        $violationType = $this->determineViolationType($reason);

        if ($violationType && $contentType) {
            \App\Actions\Trust\PenalizeViolation::run($submitter, $violationType, $contentType, null, $reason);
        }
    }

    /**
     * Determine violation type from rejection reason.
     */
    protected function determineViolationType(string $reason): ?string
    {
        $reason = strtolower($reason);

        if (str_contains($reason, 'spam') || str_contains($reason, 'duplicate')) {
            return 'spam';
        }

        if (str_contains($reason, 'inappropriate') || str_contains($reason, 'offensive')) {
            return 'major';
        }

        if (str_contains($reason, 'guidelines') || str_contains($reason, 'policy')) {
            return 'minor';
        }

        return null; // No penalty for generic rejections
    }
}
