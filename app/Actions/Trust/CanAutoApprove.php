<?php

namespace App\Actions\Trust;

use App\Models\User;
use App\Support\TrustConstants;
use Lorisleiva\Actions\Concerns\AsAction;

class CanAutoApprove
{
    use AsAction;

    /**
     * Check if user can auto-approve content.
     */
    public function handle(User $user, string $contentType = 'global'): bool
    {
        // Check if content type allows auto-approval
        if (class_exists($contentType) && in_array(\App\Concerns\Revisionable::class, class_uses_recursive($contentType))) {
            $tempInstance = new $contentType;
            if ($tempInstance->getAutoApproveMode() === 'never') {
                return false;
            }
        }

        return GetTrustBalance::run($user, $contentType) >= TrustConstants::TRUST_AUTO_APPROVED;
    }
}
