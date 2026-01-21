<?php

namespace CorvMC\Moderation\Actions\Revisions;

use CorvMC\Moderation\Actions\Trust\AwardSuccessfulContent;
use CorvMC\Moderation\Models\Revision;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class AwardTrustPointsForRevision
{
    use AsAction;

    /**
     * Award trust points for successful revision.
     */
    public function handle(Revision $revision): void
    {
        $submitter = $revision->submittedBy;
        $model = $revision->revisionable;
        $contentType = $model ? get_class($model) : null;

        if (! $contentType || ! $model) {
            Log::warning('Cannot award trust points - model not found', [
                'revision_id' => $revision->id,
            ]);

            return;
        }

        AwardSuccessfulContent::run($submitter, $model, $contentType, true);
    }
}
