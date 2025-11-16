<?php

namespace App\Actions\Revisions;

use App\Models\Revision;
use App\Models\User;
use App\Notifications\RevisionSubmittedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Lorisleiva\Actions\Concerns\AsAction;

class QueueRevisionForReview
{
    use AsAction;

    /**
     * Queue a revision for manual review.
     */
    public function handle(Revision $revision): void
    {
        $submitter = $revision->submittedBy;
        $model = $revision->revisionable;
        $contentType = $model ? get_class($model) : null;

        if (! $contentType) {
            Log::warning('Cannot queue revision for review - model not found', [
                'revision_id' => $revision->id,
            ]);

            return;
        }

        // Determine review priority based on trust level
        $workflow = \App\Actions\Trust\DetermineApprovalWorkflow::run($submitter, $contentType);

        Log::info('Revision queued for manual review', [
            'revision_id' => $revision->id,
            'priority' => $workflow['review_priority'],
            'estimated_time' => $workflow['estimated_review_time'],
        ]);

        // Notify moderators about pending revision
        $this->notifyModerators($revision, $workflow['review_priority']);
    }

    /**
     * Notify moderators about pending revisions.
     */
    protected function notifyModerators(Revision $revision, string $priority): void
    {
        try {
            // Get users with revision approval permissions
            $moderators = User::permission('approve revisions')->get();

            if ($moderators->isNotEmpty()) {
                Notification::send($moderators, new RevisionSubmittedNotification($revision, $priority));
            }
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist $e) {
            Log::warning('Permission "approve revisions" not found. Run: php artisan db:seed --class=PermissionSeeder', [
                'revision_id' => $revision->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
