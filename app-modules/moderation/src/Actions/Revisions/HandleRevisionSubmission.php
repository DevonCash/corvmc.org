<?php

namespace CorvMC\Moderation\Actions\Revisions;

use CorvMC\Bands\Models\Band;
use CorvMC\Moderation\Models\Revision;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class HandleRevisionSubmission
{
    use AsAction;

    /**
     * Handle a newly submitted revision.
     */
    public function handle(Revision $revision): void
    {
        // Guard: Don't re-process already-reviewed revisions
        if ($revision->isReviewed()) {
            Log::info('Skipping review handling - revision already reviewed', [
                'revision_id' => $revision->id,
                'status' => $revision->status,
            ]);

            return;
        }

        Log::info('Revision submitted for review', [
            'revision_id' => $revision->id,
            'model_type' => $revision->revisionable_type,
            'model_id' => $revision->revisionable_id,
            'submitted_by' => $revision->submitted_by_id,
            'changes_count' => count($revision->proposed_changes),
        ]);

        // Check if this revision can be auto-approved based on trust
        if ($this->shouldAutoApprove($revision)) {
            try {
                AutoApproveRevision::run($revision);
            } catch (\Exception $e) {
                Log::error('Auto-approval failed, falling back to manual review', [
                    'revision_id' => $revision->id,
                    'error' => $e->getMessage(),
                ]);

                // Fall back to manual review
                QueueRevisionForReview::run($revision);
            }

            return;
        }

        // Queue for manual review
        QueueRevisionForReview::run($revision);
    }

    /**
     * Determine if a revision should be auto-approved.
     */
    protected function shouldAutoApprove(Revision $revision): bool
    {
        $submitter = $revision->submittedBy;

        // Load model without global scopes (e.g., visibility) to ensure we can always check
        // Use the revisionable relationship which properly resolves morph aliases
        $model = $revision->revisionable()->withoutGlobalScopes()->first();

        // Check if model exists (could be soft-deleted or missing)
        if (! $model) {
            Log::warning('Revision model not found', [
                'revision_id' => $revision->id,
                'revisionable_type' => $revision->revisionable_type,
                'revisionable_id' => $revision->revisionable_id,
            ]);

            return false;
        }

        // Auto-approve if submitter is admin/staff with management role
        if ($submitter->hasRole(['production manager', 'admin', 'moderator'])) {
            Log::info('Auto-approving revision - user has management role', [
                'revision_id' => $revision->id,
                'user_id' => $submitter->id,
            ]);

            return true;
        }

        // Ensure the model uses the Revisionable trait
        if (! method_exists($model, 'getAutoApproveMode')) {
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
                $contentType = get_class($model);

                return $submitter->canAutoApprove($contentType);
        }
    }

    /**
     * Check if personal content should auto-approve.
     */
    protected function shouldAutoApprovePersonalContent($model, $submitter): bool
    {
        $trustPoints = $submitter->trust_points[get_class($model)] ?? 0;

        // For Band models, check ownership
        if ($model instanceof Band && $model->owner_id !== $submitter->id) {
            return false; // Only owners can auto-approve band changes
        }

        // Personal content auto-approves unless user is in poor standing
        // Allow some minor violations but not major ones
        return $trustPoints >= -5;
    }

    /**
     * Check if content should auto-approve until reported.
     */
    protected function shouldAutoApproveUntilReported($model, $submitter): bool
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
        $contentType = get_class($model);
        $trustPoints = $submitter->trust_points[$contentType] ?? 0;

        return $trustPoints >= 0; // Auto-approve if no recent violations
    }
}
