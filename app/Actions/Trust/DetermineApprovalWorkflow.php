<?php

namespace App\Actions\Trust;

use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class DetermineApprovalWorkflow
{
    use AsAction;

    /**
     * Determine approval workflow for content.
     */
    public function handle(User $user, string $contentType = 'global'): array
    {
        $trustLevel = $user->getTrustLevel($contentType);

        return [
            'requires_approval' => $trustLevel->requiresReview(),
            'auto_publish' => $trustLevel->canAutoApprove(),
            'review_priority' => $trustLevel->getReviewPriority(),
            'estimated_review_time' => $trustLevel->getEstimatedReviewTime(),
        ];
    }
}
