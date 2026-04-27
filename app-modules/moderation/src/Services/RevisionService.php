<?php

namespace CorvMC\Moderation\Services;

use App\Models\User;
use CorvMC\Moderation\Enums\ApprovalWorkflow;
use CorvMC\Moderation\Enums\RevisionStatus;
use CorvMC\Moderation\Models\Revision;
use CorvMC\Moderation\Notifications\RevisionApprovedNotification;
use CorvMC\Moderation\Notifications\RevisionRejectedNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service for managing content revisions and their approval workflow.
 * 
 * This service handles revision submission, approval, rejection, and application
 * of changes to content models.
 */
class RevisionService
{
    public function __construct(
        private TrustService $trustService
    ) {}

    /**
     * Handle submission of a new revision.
     *
     * @param array $data Revision data
     * @param User $submitter User submitting the revision
     * @return Revision The created revision
     */
    public function handleSubmission(array $data, User $submitter): Revision
    {
        return DB::transaction(function () use ($data, $submitter) {
            // Determine approval workflow based on trust
            $workflow = $this->trustService->determineApprovalWorkflow(
                $submitter,
                $data['revisionable_type']
            );

            // Create revision
            $revision = Revision::create(array_merge($data, [
                'submitted_by_id' => $submitter->id,
                'status' => RevisionStatus::Pending->value,
            ]));

            // Auto-approve if workflow allows
            if ($workflow === ApprovalWorkflow::AutoApprove) {
                $this->autoApprove($revision);
            } else {
                $this->queueForReview($revision);
            }

            return $revision;
        });
    }

    /**
     * Queue a revision for review.
     *
     * @param Revision $revision The revision to queue
     * @return Revision The updated revision
     */
    public function queueForReview(Revision $revision): Revision
    {
        $revision->update([
            'status' => RevisionStatus::Pending,
            'queued_at' => now(),
        ]);

        // Notify moderators based on workflow
        $this->notifyModerators($revision);

        return $revision;
    }

    /**
     * Approve a revision.
     *
     * @param Revision $revision The revision to approve
     * @param User|null $approvedBy User approving the revision
     * @param string $notes Optional approval notes
     * @return Revision The approved revision
     */
    public function approve(Revision $revision, ?User $approvedBy = null, string $notes = ''): Revision
    {
        if (! $revision->isPending()) {
            throw new \Exception('Revision has already been reviewed');
        }

        return DB::transaction(function () use ($revision, $approvedBy, $notes) {
            $revision->update([
                'status' => RevisionStatus::Approved,
                'approved_by' => $approvedBy?->id,
                'approved_at' => now(),
                'approval_notes' => $notes,
            ]);

            // Apply the revision changes
            $this->apply($revision);

            // Award trust points to submitter
            if ($revision->submitter) {
                $this->awardTrustPointsForRevision($revision);
            }

            // Notify submitter
            if ($revision->submitter) {
                $revision->submitter->notify(new RevisionApprovedNotification($revision));
            }

            return $revision;
        });
    }

    /**
     * Reject a revision.
     *
     * @param Revision $revision The revision to reject
     * @param User|null $rejectedBy User rejecting the revision
     * @param string $reason Reason for rejection
     * @return Revision The rejected revision
     */
    public function reject(Revision $revision, ?User $rejectedBy = null, string $reason = ''): Revision
    {
        $revision->update([
            'status' => RevisionStatus::Rejected,
            'rejected_by' => $rejectedBy?->id,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        // Notify submitter
        if ($revision->submitter) {
            $revision->submitter->notify(new RevisionRejectedNotification($revision));
        }

        return $revision;
    }

    /**
     * Auto-approve a revision from a trusted user.
     *
     * @param Revision $revision The revision to auto-approve
     * @return Revision The approved revision
     */
    public function autoApprove(Revision $revision): Revision
    {
        return $this->approve($revision, null, 'Auto-approved based on trust level');
    }

    /**
     * Apply revision changes to the target model.
     *
     * @param Revision $revision The revision to apply
     * @return bool Success status
     */
    public function apply(Revision $revision): bool
    {
        if ($revision->status !== RevisionStatus::Approved) {
            throw new \Exception('Cannot apply non-approved revision');
        }

        // Bypass global scopes (e.g., MemberVisibilityScope) when resolving revisionable
        $model = $revision->revisionable()->withoutGlobalScopes()->first();
        if (!$model) {
            throw new \Exception('Revisionable model not found');
        }

        // Apply changes — use saveQuietly to avoid re-triggering revision observers
        $changes = $revision->proposed_changes;
        foreach ($changes as $field => $value) {
            $model->$field = $value;
        }

        $model->saveQuietly();

        $revision->update(['applied_at' => now()]);

        return true;
    }

    /**
     * Bulk approve revisions from trusted users.
     *
     * @param int $trustThreshold Minimum trust level for auto-approval
     * @param int $limit Maximum revisions to process
     * @return int Number of revisions approved
     */
    public function bulkApproveFromTrustedUsers(int $trustThreshold = 100, int $limit = 50): int
    {
        $revisions = Revision::where('status', RevisionStatus::Pending)
            ->whereHas('submitter.trustBalances', function ($query) use ($trustThreshold) {
                $query->where('balance', '>=', $trustThreshold);
            })
            ->limit($limit)
            ->get();

        $approved = 0;
        foreach ($revisions as $revision) {
            $this->autoApprove($revision);
            $approved++;
        }

        return $approved;
    }

    /**
     * Get summary of pending revisions.
     *
     * @return array Summary statistics
     */
    public function getPendingRevisionsSummary(): array
    {
        $pending = Revision::where('status', RevisionStatus::Pending);
        
        return [
            'total_pending' => $pending->count(),
            'by_workflow' => $pending->get()->groupBy('approval_workflow')->map->count(),
            'by_type' => $pending->get()->groupBy('revisionable_type')->map->count(),
            'oldest_pending' => $pending->oldest()->first()?->created_at,
            'requiring_admin' => $pending->where('approval_workflow', ApprovalWorkflow::RequireAdminReview)->count(),
        ];
    }

    /**
     * Clean up old rejected revisions.
     *
     * @param int $daysOld Age threshold in days
     * @return int Number of revisions deleted
     */
    public function cleanupOldRevisions(int $daysOld = 90): int
    {
        return Revision::where('status', RevisionStatus::Rejected)
            ->where('rejected_at', '<', now()->subDays($daysOld))
            ->delete();
    }

    /**
     * Award trust points for an approved revision.
     *
     * @param Revision $revision The approved revision
     */
    protected function awardTrustPointsForRevision(Revision $revision): void
    {
        if (!$revision->submitter) {
            return;
        }

        // Determine points based on revision complexity
        $changeCount = count($revision->proposed_changes);
        $points = min(10 + ($changeCount * 2), 30); // Max 30 points

        $this->trustService->awardPoints(
            $revision->submitter,
            $points,
            $revision->revisionable_type,
            'revision_approved',
            $revision->id,
            'Revision approved'
        );
    }

    /**
     * Notify appropriate moderators about a pending revision.
     *
     * @param Revision $revision The revision needing review
     */
    protected function notifyModerators(Revision $revision): void
    {
        // Get moderators based on workflow
        $moderators = match($revision->approval_workflow) {
            ApprovalWorkflow::RequireAdminReview => User::role('admin')->get(),
            ApprovalWorkflow::TrustedReview => User::role(['admin', 'moderator'])->get(),
            default => User::role('moderator')->get(),
        };

        // Send notifications
        foreach ($moderators as $moderator) {
            // Notification implementation would go here
        }
    }
}