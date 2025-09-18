<?php

namespace App\Services;

use App\Models\Revision;
use App\Models\User;
use App\Notifications\RevisionApprovedNotification;
use App\Notifications\RevisionRejectedNotification;
use App\Notifications\RevisionSubmittedNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Revision Service
 * 
 * Central service for managing model revision workflows.
 * Handles submission, approval, rejection, and integration with the trust system.
 */
class RevisionService
{
    protected TrustService $trustService;

    public function __construct(TrustService $trustService)
    {
        $this->trustService = $trustService;
    }

    /**
     * Handle a newly submitted revision.
     */
    public function handleRevisionSubmission(Revision $revision): void
    {
        Log::info('Revision submitted for review', [
            'revision_id' => $revision->id,
            'model_type' => $revision->revisionable_type,
            'model_id' => $revision->revisionable_id,
            'submitted_by' => $revision->submitted_by_id,
            'changes_count' => count($revision->proposed_changes),
        ]);

        // Check if this revision can be auto-approved based on trust
        if ($this->shouldAutoApprove($revision)) {
            $this->autoApproveRevision($revision);
            return;
        }

        // Queue for manual review
        $this->queueForReview($revision);
    }

    /**
     * Determine if a revision should be auto-approved.
     */
    protected function shouldAutoApprove(Revision $revision): bool
    {
        $submitter = $revision->submittedBy;
        $model = $revision->revisionable;

        // Check if model exists (could be soft-deleted or missing)
        if (!$model) {
            \Log::warning('Revision model not found', [
                'revision_id' => $revision->id,
                'revisionable_type' => $revision->revisionable_type,
                'revisionable_id' => $revision->revisionable_id,
            ]);
            return false;
        }

        // Get auto-approval mode from the model
        $autoApproveMode = $model->getAutoApproveMode();
        
        // Handle different auto-approval modes
        switch ($autoApproveMode) {
            case 'never':
                // Organizational content always requires manual approval
                return false;
                
            case 'personal':
                // Personal content auto-approves unless user is in poor standing
                return $this->shouldAutoApprovePersonalContent($model, $submitter);
                
            case 'untilReport':
                // Auto-approve until content receives credible reports
                return $this->shouldAutoApproveUntilReported($model, $submitter);
                
            case 'trusted':
            default:
                // Standard trust-based auto-approval
                $contentType = $this->getContentTypeForModel($model);
                return $this->trustService->canAutoApprove($submitter, $contentType);
        }
    }
    
    /**
     * Check if personal content should auto-approve.
     */
    protected function shouldAutoApprovePersonalContent($model, User $submitter): bool
    {
        $contentType = $this->getContentTypeForModel($model);
        $trustPoints = $submitter->trust_points[$contentType] ?? 0;
        
        // For Band models, check ownership
        if ($model instanceof \App\Models\Band && $model->owner_id !== $submitter->id) {
            return false; // Only owners can auto-approve band changes
        }
        
        // Personal content auto-approves unless user is in poor standing
        // Allow some minor violations but not major ones
        return $trustPoints >= -5;
    }
    
    /**
     * Check if content should auto-approve until reported.
     */
    protected function shouldAutoApproveUntilReported($model, User $submitter): bool
    {
        // Check if the user or their recent content has credible reports
        if (method_exists($model, 'reports')) {
            $hasRecentUpheldReports = $model->reports()
                ->where('status', 'upheld')
                ->where('created_at', '>', now()->subMonth())
                ->exists();
                
            if ($hasRecentUpheldReports) {
                return false; // Requires manual review due to recent reports
            }
        }
        
        // Check submitter's recent violations
        $contentType = $this->getContentTypeForModel($model);
        $trustPoints = $submitter->trust_points[$contentType] ?? 0;
        
        return $trustPoints >= 0; // Auto-approve if no recent violations
    }

    /**
     * Auto-approve a revision based on trust level.
     */
    public function autoApproveRevision(Revision $revision): bool
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
            $this->applyRevision($revision);

            // Award trust points for successful content
            $this->awardTrustPoints($revision);

            // Send notification
            $revision->submittedBy->notify(new RevisionApprovedNotification($revision));

            return true;
        });
    }

    /**
     * Queue a revision for manual review.
     */
    protected function queueForReview(Revision $revision): void
    {
        $submitter = $revision->submittedBy;
        $model = $revision->revisionable;
        $contentType = $this->getContentTypeForModel($model);
        
        if (!$contentType) {
            \Log::warning('Cannot queue revision for review - model not found', [
                'revision_id' => $revision->id,
            ]);
            return;
        }
        
        // Determine review priority based on trust level
        $workflow = $this->trustService->determineApprovalWorkflow($submitter, $contentType);
        
        Log::info('Revision queued for manual review', [
            'revision_id' => $revision->id,
            'priority' => $workflow['review_priority'],
            'estimated_time' => $workflow['estimated_review_time'],
        ]);

        // Notify moderators about pending revision
        $this->notifyModerators($revision, $workflow['review_priority']);
    }

    /**
     * Approve a revision manually.
     */
    public function approveRevision(Revision $revision, User $reviewer, string $reason = null): bool
    {
        if (!$revision->isPending()) {
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
            $this->applyRevision($revision);

            // Award trust points
            $this->awardTrustPoints($revision);

            // Send notification
            $revision->submittedBy->notify(new RevisionApprovedNotification($revision));

            return true;
        });
    }

    /**
     * Reject a revision.
     */
    public function rejectRevision(Revision $revision, User $reviewer, string $reason): bool
    {
        if (!$revision->isPending()) {
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
     * Apply an approved revision to its model.
     */
    protected function applyRevision(Revision $revision): bool
    {
        $model = $revision->revisionable;
        
        if (!$model) {
            throw new \InvalidArgumentException('Revisionable model not found');
        }

        // Use forceUpdate to bypass revision system for the actual update
        return $model->forceUpdate($revision->proposed_changes);
    }

    /**
     * Award trust points for successful revision.
     */
    protected function awardTrustPoints(Revision $revision): void
    {
        $submitter = $revision->submittedBy;
        $model = $revision->revisionable;
        $contentType = $this->getContentTypeForModel($model);

        if (!$contentType || !$model) {
            \Log::warning('Cannot award trust points - model not found', [
                'revision_id' => $revision->id,
            ]);
            return;
        }

        $this->trustService->awardSuccessfulContent($submitter, $model, $contentType, true);
    }

    /**
     * Handle trust penalty for rejected revisions.
     */
    protected function handleRejectionPenalty(Revision $revision, string $reason): void
    {
        $submitter = $revision->submittedBy;
        $contentType = $this->getContentTypeForModel($revision->revisionable);
        
        // Determine violation type based on rejection reason
        $violationType = $this->determineViolationType($reason);
        
        if ($violationType) {
            $this->trustService->penalizeViolation($submitter, $violationType, $contentType, $reason);
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

    /**
     * Notify moderators about pending revisions.
     */
    protected function notifyModerators(Revision $revision, string $priority): void
    {
        // Get users with revision approval permissions
        $moderators = User::permission('approve revisions')->get();
        
        if ($moderators->isNotEmpty()) {
            Notification::send($moderators, new RevisionSubmittedNotification($revision, $priority));
        }
    }

    /**
     * Get content type for trust system based on model.
     */
    protected function getContentTypeForModel(?Model $model): ?string
    {
        return $model ? get_class($model) : null;
    }

    /**
     * Get pending revisions summary.
     */
    public function getPendingRevisionsSummary(): array
    {
        $pending = Revision::pending();
        
        return [
            'total' => $pending->count(),
            'by_type' => $pending->selectRaw('revisionable_type, COUNT(*) as count')
                              ->groupBy('revisionable_type')
                              ->pluck('count', 'revisionable_type')
                              ->toArray(),
            'by_priority' => $this->getPendingByPriority(),
            'oldest' => $pending->oldest()->first(),
        ];
    }

    /**
     * Get pending revisions grouped by priority.
     */
    protected function getPendingByPriority(): array
    {
        $pending = Revision::pending()->with('submittedBy')->get();
        $priority = ['urgent' => 0, 'fast-track' => 0, 'standard' => 0];
        
        foreach ($pending as $revision) {
            $submitter = $revision->submittedBy;
            $model = $revision->revisionable;
            $contentType = $this->getContentTypeForModel($model);
            
            $workflow = $this->trustService->determineApprovalWorkflow($submitter, $contentType);
            $priority[$workflow['review_priority']] = ($priority[$workflow['review_priority']] ?? 0) + 1;
        }
        
        return $priority;
    }

    /**
     * Bulk approve revisions from trusted users.
     */
    public function bulkApproveFromTrustedUsers(User $reviewer): int
    {
        $trustedRevisions = Revision::pending()
            ->whereHas('submittedBy', function ($query) {
                // Get revisions from users with fast-track approval
                $query->whereRaw("JSON_EXTRACT(trust_points, '$.global') >= ?", [
                    $this->trustService::TRUST_TRUSTED
                ]);
            })
            ->get();

        $approved = 0;
        foreach ($trustedRevisions as $revision) {
            try {
                $this->approveRevision($revision, $reviewer, 'Bulk approved - trusted user');
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

    /**
     * Clean up old rejected revisions.
     */
    public function cleanupOldRevisions(int $daysOld = 90): int
    {
        return Revision::where('status', Revision::STATUS_REJECTED)
            ->where('created_at', '<', now()->subDays($daysOld))
            ->delete();
    }
}