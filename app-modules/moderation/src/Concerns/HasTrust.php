<?php

namespace CorvMC\Moderation\Concerns;

use CorvMC\Moderation\Enums\TrustLevel;use CorvMC\Moderation\Models\TrustAchievement;
use CorvMC\Moderation\Models\TrustTransaction;
use CorvMC\Moderation\Models\UserTrustBalance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

trait HasTrust
{
    /**
     * Get the user's trust balances.
     */
    public function trustBalances(): HasMany
    {
        return $this->hasMany(UserTrustBalance::class);
    }

    /**
     * Get the user's trust transactions.
     */
    public function trustTransactions(): HasMany
    {
        return $this->hasMany(TrustTransaction::class);
    }

    /**
     * Get the user's trust achievements.
     */
    public function trustAchievements(): HasMany
    {
        return $this->hasMany(TrustAchievement::class);
    }

    /**
     * Get user's current trust balance for a content type.
     */
    public function getTrustBalance(string $contentType = 'global'): int
    {
        return UserTrustBalance::where('user_id', $this->id)
            ->where('content_type', $contentType)
            ->value('balance') ?? 0;
    }

    /**
     * Get the current trust level for a content type.
     */
    public function getTrustLevel(string $contentType = 'global'): TrustLevel
    {
        $points = $this->getTrustBalance($contentType);

        return TrustLevel::fromPoints($points);
    }

    /**
     * Get trust level display information.
     */
    public function getTrustLevelInfo(string $contentType = 'global'): array
    {
        $points = $this->getTrustBalance($contentType);
        $level = $this->getTrustLevel($contentType);

        return [
            'level' => $level->value,
            'level_enum' => $level,
            'points' => $points,
            'content_type' => $contentType,
            'can_auto_approve' => $level->canAutoApprove(),
            'fast_track' => $level->isFastTrack(),
            'next_level' => $level->getNextLevel()?->value,
            'next_level_enum' => $level->getNextLevel(),
            'points_needed' => $level->getPointsNeededForNext($points),
            'estimated_review_time' => $level->getEstimatedReviewTime(),
            'review_priority' => $level->getReviewPriority(),
        ];
    }

    /**
     * Get trust badge information for display.
     */
    public function getTrustBadge(string $contentType = 'global'): ?array
    {
        $level = $this->getTrustLevel($contentType);
        $typeName = $this->getContentTypeName($contentType);

        return $level->getBadgeInfo($typeName);
    }

    /**
     * Get human-readable content type name.
     */
    protected function getContentTypeName(string $contentType): string
    {
        return match ($contentType) {
            'App\\Models\\Event' => 'Event Organizer',
            'App\\Models\\MemberProfile' => 'Profile Creator',
            'App\\Models\\Band' => 'Band Manager',
            'global' => 'Community Member',
            default => 'Content Creator'
        };
    }

    /**
     * Check if user can auto-approve content.
     */
    public function canAutoApprove(string|Model $contentType = 'global'): bool
    {
        // Handle Model instance
        if ($contentType instanceof Model) {
            $contentType = get_class($contentType);
        }

        // Check if content type allows auto-approval
        if (class_exists($contentType) && in_array(Revisionable::class, class_uses_recursive($contentType))) {
            $tempInstance = new $contentType;
            if ($tempInstance->getAutoApproveMode() === 'never') {
                return false;
            }
        }

        $level = $this->getTrustLevel($contentType);

        return $level->canAutoApprove();
    }

    /**
     * Get trust transaction history for user.
     */
    public function getTrustTransactionHistory(?string $contentType = null, ?int $limit = 50): Collection
    {
        $query = $this->trustTransactions();

        if ($contentType) {
            $query->where('content_type', $contentType);
        }

        return $query->latest()
            ->limit($limit)
            ->get();
    }
}
