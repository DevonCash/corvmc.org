<?php

namespace App\Actions\Trust;

use App\Models\TrustAchievement;
use App\Models\TrustTransaction;
use App\Models\User;
use App\Models\UserTrustBalance;
use App\Support\TrustConstants;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class AwardTrustPoints
{
    use AsAction;

    /**
     * Award trust points (transaction-safe).
     */
    public function handle(
        User $user,
        int $points,
        string $contentType,
        string $sourceType,
        ?int $sourceId = null,
        string $reason = '',
        ?User $awardedBy = null
    ): TrustTransaction {
        return DB::transaction(function () use ($user, $points, $contentType, $sourceType, $sourceId, $reason, $awardedBy) {
            // Lock balance record for update
            $balance = UserTrustBalance::lockForUpdate()
                ->firstOrCreate(
                    ['user_id' => $user->id, 'content_type' => $contentType],
                    ['balance' => 0]
                );

            $oldBalance = $balance->balance;
            $newBalance = $oldBalance + $points;

            // Special case: content-type trust can't go below 0, but global can
            if ($contentType !== 'global') {
                $newBalance = max(0, $newBalance);
            }

            $balance->balance = $newBalance;
            $balance->save();

            // Check if achievement unlocked
            $this->checkAchievement($user, $contentType, $oldBalance, $newBalance);

            // Record transaction
            $transaction = TrustTransaction::create([
                'user_id' => $user->id,
                'content_type' => $contentType,
                'points' => $points,
                'balance_after' => $newBalance,
                'reason' => $reason,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'awarded_by_id' => $awardedBy?->id,
            ]);

            // Update global trust if this is a specific content type
            if ($contentType !== 'global') {
                $this->updateGlobalTrust($user);
            }

            return $transaction;
        });
    }

    /**
     * Check and record achievement if threshold crossed.
     */
    protected function checkAchievement(User $user, string $contentType, int $oldBalance, int $newBalance): void
    {
        $levels = [
            'trusted' => TrustConstants::TRUST_TRUSTED,
            'verified' => TrustConstants::TRUST_VERIFIED,
            'auto_approved' => TrustConstants::TRUST_AUTO_APPROVED,
        ];

        foreach ($levels as $level => $threshold) {
            // Check if user just crossed threshold
            if ($oldBalance < $threshold && $newBalance >= $threshold) {
                TrustAchievement::firstOrCreate([
                    'user_id' => $user->id,
                    'content_type' => $contentType,
                    'level' => $level,
                ], [
                    'achieved_at' => now(),
                ]);
            }
        }
    }

    /**
     * Update global trust as weighted average of content-type trusts.
     */
    protected function updateGlobalTrust(User $user): void
    {
        $contentTypes = [
            'App\\Models\\CommunityEvent',
            'App\\Models\\MemberProfile',
            'App\\Models\\Band',
            'App\\Models\\Production',
        ];

        $totalPoints = 0;
        $activeTypes = 0;

        foreach ($contentTypes as $type) {
            $points = GetTrustBalance::run($user, $type);

            if ($points > 0) {
                $totalPoints += $points;
                $activeTypes++;
            }
        }

        $globalPoints = $activeTypes > 0 ? intval($totalPoints / $activeTypes) : 0;

        // Update global balance directly (no transaction needed, already in parent transaction)
        UserTrustBalance::updateOrCreate(
            ['user_id' => $user->id, 'content_type' => 'global'],
            ['balance' => $globalPoints]
        );
    }
}
