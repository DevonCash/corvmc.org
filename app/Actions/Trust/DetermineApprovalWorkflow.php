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
        $trustLevel = GetTrustLevel::run($user, $contentType);

        switch ($trustLevel) {
            case 'auto-approved':
                return [
                    'requires_approval' => false,
                    'auto_publish' => true,
                    'review_priority' => 'none',
                    'estimated_review_time' => 0,
                ];

            case 'verified':
            case 'trusted':
                return [
                    'requires_approval' => true,
                    'auto_publish' => false,
                    'review_priority' => 'fast-track',
                    'estimated_review_time' => 24, // hours
                ];

            default:
                return [
                    'requires_approval' => true,
                    'auto_publish' => false,
                    'review_priority' => 'standard',
                    'estimated_review_time' => 72, // hours
                ];
        }
    }
}
