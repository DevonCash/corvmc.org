<?php

namespace App\Actions\Revisions;

use App\Models\Revision;
use App\Services\TrustService;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class AwardTrustPointsForRevision
{
    use AsAction;

    public function __construct(
        protected TrustService $trustService
    ) {}

    /**
     * Award trust points for successful revision.
     */
    public function handle(Revision $revision): void
    {
        $submitter = $revision->submittedBy;
        $model = $revision->revisionable;
        $contentType = $model ? get_class($model) : null;

        if (!$contentType || !$model) {
            Log::warning('Cannot award trust points - model not found', [
                'revision_id' => $revision->id,
            ]);
            return;
        }

        $this->trustService->awardSuccessfulContent($submitter, $model, $contentType, true);
    }
}
