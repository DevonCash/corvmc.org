<?php

namespace CorvMC\Moderation\Services;

use App\Models\User;
use CorvMC\Moderation\Enums\ApprovalWorkflow;
use CorvMC\Moderation\Models\TrustAchievement;
use CorvMC\Moderation\Models\TrustTransaction;
use CorvMC\Moderation\Models\UserTrustBalance;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service for managing user trust levels and trust points.
 * 
 * This service handles trust point awards, penalties, and trust level calculations
 * for content moderation and user reputation management.
 */
class TrustService
{
    /**
     * Award trust points to a user.
     *
     * @param User $user The user to award points to
     * @param int $points Number of points to award (can be negative for penalties)
     * @param string $contentType Type of content (e.g., 'event', 'band', 'production')
     * @param string $sourceType Source of the points (e.g., 'revision_approved', 'violation')
     * @param int|null $sourceId Optional ID of the source entity
     * @param string $reason Optional reason for the award
     * @param User|null $awardedBy Optional user who awarded the points
     * @return TrustTransaction The created transaction record
     */
    public function awardPoints(
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
            $newBalance = max(0, $oldBalance + $points);

            // Update balance
            $balance->update(['balance' => $newBalance]);

            // Create transaction record
            $transaction = TrustTransaction::create([
                'user_id' => $user->id,
                'content_type' => $contentType,
                'points' => $points,
                'balance_before' => $oldBalance,
                'balance_after' => $newBalance,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'reason' => $reason,
                'awarded_by' => $awardedBy?->id,
            ]);

            // Check for achievements
            $this->checkAchievements($user, $contentType, $newBalance);

            return $transaction;
        });
    }

    /**
     * Penalize a user for a content violation.
     *
     * @param User $user The user to penalize
     * @param string $contentType Type of content violated
     * @param int $penaltyPoints Points to deduct (should be positive, will be negated)
     * @param string $reason Reason for the penalty
     * @return TrustTransaction The penalty transaction
     */
    public function penalizeViolation(User $user, string $contentType, int $penaltyPoints, string $reason): TrustTransaction
    {
        return $this->awardPoints(
            $user,
            -abs($penaltyPoints), // Ensure negative
            $contentType,
            'violation',
            null,
            $reason
        );
    }

    /**
     * Award points for successful content creation/revision.
     *
     * @param User $user The user to reward
     * @param string $contentType Type of content
     * @param int $contentId ID of the content
     * @param int $points Points to award
     * @return TrustTransaction The award transaction
     */
    public function awardSuccessfulContent(User $user, string $contentType, int $contentId, int $points = 10): TrustTransaction
    {
        return $this->awardPoints(
            $user,
            $points,
            $contentType,
            'content_approved',
            $contentId,
            'Content approved successfully'
        );
    }

    /**
     * Handle a content violation report.
     *
     * @param User $user The violating user
     * @param string $contentType Type of content
     * @param string $violationType Type of violation
     * @param int|null $contentId Optional content ID
     * @return TrustTransaction The penalty transaction
     */
    public function handleContentViolation(
        User $user,
        string $contentType,
        string $violationType,
        ?int $contentId = null
    ): TrustTransaction {
        // Determine penalty based on violation type
        $penaltyPoints = match($violationType) {
            'spam' => 50,
            'offensive' => 30,
            'misinformation' => 20,
            'minor' => 10,
            default => 15,
        };

        return $this->awardPoints(
            $user,
            -$penaltyPoints,
            $contentType,
            'violation',
            $contentId,
            "Content violation: {$violationType}"
        );
    }

    /**
     * Reset trust points for a user in a specific content type.
     *
     * @param User $user The user to reset
     * @param string $contentType Content type to reset
     * @param string $reason Reason for reset
     * @return void
     */
    public function resetTrustPoints(User $user, string $contentType, string $reason = 'Administrative reset'): void
    {
        DB::transaction(function () use ($user, $contentType, $reason) {
            $balance = UserTrustBalance::where('user_id', $user->id)
                ->where('content_type', $contentType)
                ->first();

            if ($balance) {
                $oldBalance = $balance->balance;
                
                // Create reset transaction
                TrustTransaction::create([
                    'user_id' => $user->id,
                    'content_type' => $contentType,
                    'points' => -$oldBalance,
                    'balance_before' => $oldBalance,
                    'balance_after' => 0,
                    'source_type' => 'reset',
                    'reason' => $reason,
                ]);

                // Reset balance
                $balance->update(['balance' => 0]);
            }
        });
    }

    /**
     * Get users by trust level for a content type.
     *
     * @param string $contentType Content type
     * @param int $minTrust Minimum trust level
     * @param int|null $maxTrust Optional maximum trust level
     * @return Collection Users within the trust range
     */
    public function getUsersByTrustLevel(string $contentType, int $minTrust, ?int $maxTrust = null): Collection
    {
        $query = User::whereHas('trustBalances', function ($q) use ($contentType, $minTrust, $maxTrust) {
            $q->where('content_type', $contentType)
              ->where('balance', '>=', $minTrust);
            
            if ($maxTrust !== null) {
                $q->where('balance', '<=', $maxTrust);
            }
        });

        return $query->get();
    }

    /**
     * Determine the approval workflow based on user's trust level.
     *
     * @param User $user The user submitting content
     * @param string $contentType Type of content
     * @return ApprovalWorkflow The workflow to use
     */
    public function determineApprovalWorkflow(User $user, string $contentType): ApprovalWorkflow
    {
        $balance = UserTrustBalance::where('user_id', $user->id)
            ->where('content_type', $contentType)
            ->first();

        $trustPoints = $balance?->balance ?? 0;

        // Staff roles always auto-approve
        if ($user->hasRole(['admin', 'production manager'])) {
            return ApprovalWorkflow::AutoApprove;
        }

        // Determine based on trust points
        if ($trustPoints >= 100) {
            return ApprovalWorkflow::AutoApprove;
        } elseif ($trustPoints >= 50) {
            return ApprovalWorkflow::TrustedReview;
        } elseif ($trustPoints < -50) {
            return ApprovalWorkflow::RequireAdminReview;
        } else {
            return ApprovalWorkflow::StandardReview;
        }
    }

    /**
     * Bulk award points for past approved content.
     *
     * @param string $contentType Content type to process
     * @param int $pointsPerItem Points to award per item
     * @param int $limit Maximum items to process
     * @return int Number of users awarded
     */
    public function bulkAwardPastContent(string $contentType, int $pointsPerItem = 5, int $limit = 1000): int
    {
        // This would need to be implemented based on specific content models
        // For now, returning 0 as placeholder
        return 0;
    }

    /**
     * Check and award achievements based on trust balance.
     *
     * @param User $user The user to check
     * @param string $contentType Content type
     * @param int $balance Current balance
     */
    protected function checkAchievements(User $user, string $contentType, int $balance): void
    {
        $achievements = [
            25 => 'contributor',
            50 => 'trusted_contributor',
            100 => 'expert_contributor',
            250 => 'master_contributor',
        ];

        foreach ($achievements as $threshold => $achievementType) {
            if ($balance >= $threshold) {
                TrustAchievement::firstOrCreate([
                    'user_id' => $user->id,
                    'content_type' => $contentType,
                    'achievement_type' => $achievementType,
                ], [
                    'level' => $threshold,
                ]);
            }
        }
    }

    /**
     * Calculate overall trust level from points.
     *
     * @param int $points Total trust points
     * @return string Trust level label
     */
    protected function calculateTrustLevel(int $points): string
    {
        if ($points >= 250) return 'master';
        if ($points >= 100) return 'expert';
        if ($points >= 50) return 'trusted';
        if ($points >= 25) return 'contributor';
        if ($points >= 0) return 'member';
        return 'restricted';
    }
}