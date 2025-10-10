<?php

namespace App\Actions\Revisions;

use App\Models\Revision;
use App\Services\TrustService;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class HandleRevisionSubmission
{
    use AsAction;

    public function __construct(
        protected TrustService $trustService
    ) {}

    /**
     * Handle a newly submitted revision.
     */
    public function handle(Revision $revision): void
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
            AutoApproveRevision::run($revision);
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
        $model = $revision->revisionable;

        // Check if model exists (could be soft-deleted or missing)
        if (!$model) {
            Log::warning('Revision model not found', [
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
                $contentType = get_class($model);
                return $this->trustService->canAutoApprove($submitter, $contentType);
        }
    }

    /**
     * Check if personal content should auto-approve.
     */
    protected function shouldAutoApprovePersonalContent($model, $submitter): bool
    {
        $trustPoints = $submitter->trust_points[get_class($model)] ?? 0;

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
