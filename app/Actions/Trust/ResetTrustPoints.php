<?php

namespace App\Actions\Trust;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class ResetTrustPoints
{
    use AsAction;

    /**
     * Reset user's trust (admin function).
     */
    public function handle(User $user, string $contentType = 'global', string $reason = 'Admin reset', ?User $admin = null): void
    {
        $currentBalance = $user->getTrustBalance($contentType);

        if ($currentBalance != 0) {
            AwardTrustPoints::run(
                $user,
                -$currentBalance, // Negative of current balance
                $contentType,
                'reset',
                null,
                "Admin reset: {$reason}",
                $admin
            );

            Log::warning('Trust points reset by admin', [
                'user_id' => $user->id,
                'content_type' => $contentType,
                'old_points' => $currentBalance,
                'reason' => $reason,
                'admin_id' => $admin?->id,
            ]);
        }
    }
}
