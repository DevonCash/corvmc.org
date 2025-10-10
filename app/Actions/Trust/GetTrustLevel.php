<?php

namespace App\Actions\Trust;

use App\Models\User;
use App\Support\TrustConstants;
use Lorisleiva\Actions\Concerns\AsAction;

class GetTrustLevel
{
    use AsAction;

    /**
     * Get the current trust level.
     */
    public function handle(User $user, string $contentType = 'global'): string
    {
        $points = GetTrustBalance::run($user, $contentType);

        if ($points >= TrustConstants::TRUST_AUTO_APPROVED) {
            return 'auto-approved';
        } elseif ($points >= TrustConstants::TRUST_VERIFIED) {
            return 'verified';
        } elseif ($points >= TrustConstants::TRUST_TRUSTED) {
            return 'trusted';
        }

        return 'pending';
    }
}
