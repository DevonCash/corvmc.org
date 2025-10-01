<?php

namespace App\Services;

use App\Models\TrustAchievement;
use App\Models\TrustTransaction;
use App\Models\User;
use App\Models\UserTrustBalance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Unified Trust System for Content Moderation
 *
 * Transaction-safe trust management with full audit trail.
 * Replaces JSON-based storage with dedicated tables.
 */
class TrustService
{
    /**
     * Trust level thresholds
     */
    const TRUST_TRUSTED = 5;
    const TRUST_VERIFIED = 15;
    const TRUST_AUTO_APPROVED = 30;

    /**
     * Trust point values
     */
    const POINTS_SUCCESSFUL_CONTENT = 1;
    const POINTS_MINOR_VIOLATION = -3;
    const POINTS_MAJOR_VIOLATION = -5;
    const POINTS_SPAM_VIOLATION = -10;

    /**
     * Get user's current trust balance.
     */
    public function getBalance(User $user, string $contentType = 'global'): int
    {
        return UserTrustBalance::where('user_id', $user->id)
            ->where('content_type', $contentType)
            ->value('balance') ?? 0;
    }

    /**
     * Alias for getBalance() - maintains backward compatibility.
     */
    public function getTrustPoints(User $user, string $contentType = 'global'): int
    {
        return $this->getBalance($user, $contentType);
    }

    /**
     * Get the current trust level.
     */
    public function getTrustLevel(User $user, string $contentType = 'global'): string
    {
        $points = $this->getBalance($user, $contentType);

        if ($points >= self::TRUST_AUTO_APPROVED) {
            return 'auto-approved';
        } elseif ($points >= self::TRUST_VERIFIED) {
            return 'verified';
        } elseif ($points >= self::TRUST_TRUSTED) {
            return 'trusted';
        }

        return 'pending';
    }

    /**
     * Award trust points (transaction-safe).
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
     * Award points for successful content.
     */
    public function awardSuccessfulContent(User $user, Model $content, string $contentType = null, bool $forceAward = false): void
    {
        $contentType = $contentType ?? $this->getContentTypeFromModel($content);

        // Only award if content should be evaluated
        if (!$forceAward && !$this->shouldEvaluateContent($content)) {
            return;
        }

        // Check for upheld reports
        if (method_exists($content, 'reports')) {
            $hasUpheldReports = $content->reports()
                ->where('status', 'upheld')
                ->exists();

            if (!$hasUpheldReports) {
                $this->awardPoints(
                    $user,
                    self::POINTS_SUCCESSFUL_CONTENT,
                    $contentType,
                    'successful_content',
                    $content->id,
                    'Successful content: ' . ($content->title ?? $content->name ?? $content->id)
                );

                Log::info('Trust points awarded for successful content', [
                    'user_id' => $user->id,
                    'content_type' => $contentType,
                    'content_id' => $content->id,
                    'points_awarded' => self::POINTS_SUCCESSFUL_CONTENT,
                    'new_total' => $this->getBalance($user, $contentType)
                ]);
            }
        }
    }

    /**
     * Penalize user for violation.
     */
    public function penalizeViolation(
        User $user,
        string $violationType,
        string $contentType = 'global',
        ?int $sourceId = null,
        string $reason = '',
        ?User $penalizedBy = null
    ): void {
        $points = match($violationType) {
            'spam' => self::POINTS_SPAM_VIOLATION,
            'major' => self::POINTS_MAJOR_VIOLATION,
            'minor' => self::POINTS_MINOR_VIOLATION,
            default => self::POINTS_MINOR_VIOLATION,
        };

        $this->awardPoints(
            $user,
            $points,
            $contentType,
            "{$violationType}_violation",
            $sourceId,
            "Violation: {$violationType} - {$reason}",
            $penalizedBy
        );

        Log::warning('Trust points deducted for violation', [
            'user_id' => $user->id,
            'content_type' => $contentType,
            'violation_type' => $violationType,
            'points_deducted' => abs($points),
            'reason' => $reason,
            'new_total' => $this->getBalance($user, $contentType)
        ]);
    }

    /**
     * Handle content violation and adjust trust points accordingly.
     */
    public function handleContentViolation(User $user, Model $content, string $violationType, string $contentType = 'global'): void
    {
        $reason = "Content violation for " . ($content->title ?? $content->name ?? class_basename($content));

        $this->penalizeViolation($user, $violationType, $contentType, $content->id, $reason);

        Log::warning('Content violation handled', [
            'user_id' => $user->id,
            'content_type' => $contentType,
            'content_id' => $content->id,
            'violation_type' => $violationType,
            'new_trust_points' => $this->getBalance($user, $contentType)
        ]);
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
            'App\\Models\\Production'
        ];

        $totalPoints = 0;
        $activeTypes = 0;

        foreach ($contentTypes as $type) {
            $points = $this->getBalance($user, $type);

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

    /**
     * Check and record achievement if threshold crossed.
     */
    protected function checkAchievement(User $user, string $contentType, int $oldBalance, int $newBalance): void
    {
        $levels = [
            'trusted' => self::TRUST_TRUSTED,
            'verified' => self::TRUST_VERIFIED,
            'auto_approved' => self::TRUST_AUTO_APPROVED,
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
     * Determine if content should be evaluated for trust.
     */
    protected function shouldEvaluateContent(Model $content): bool
    {
        if ($content instanceof \App\Models\CommunityEvent) {
            return $content->isPublished() && !$content->isUpcoming();
        }

        if ($content instanceof \App\Models\Production) {
            return $content->status === 'completed';
        }

        if ($content instanceof \App\Models\MemberProfile || $content instanceof \App\Models\Band) {
            return true;
        }

        return true;
    }

    /**
     * Check if user can auto-approve content.
     */
    public function canAutoApprove(User $user, string $contentType = 'global'): bool
    {
        // Check if content type allows auto-approval
        if (class_exists($contentType) && in_array(\App\Concerns\Revisionable::class, class_uses_recursive($contentType))) {
            $tempInstance = new $contentType();
            if ($tempInstance->getAutoApproveMode() === 'never') {
                return false;
            }
        }

        return $this->getBalance($user, $contentType) >= self::TRUST_AUTO_APPROVED;
    }

    /**
     * Check if user gets fast-track approval (trusted+ users).
     */
    public function getFastTrackApproval(User $user, string $contentType = 'global'): bool
    {
        return $this->getBalance($user, $contentType) >= self::TRUST_TRUSTED;
    }

    /**
     * Get users by trust level for queries/admin.
     */
    public function getUsersByTrustLevel(string $level, string $contentType = 'global'): Collection
    {
        $minPoints = match($level) {
            'auto-approved' => self::TRUST_AUTO_APPROVED,
            'verified' => self::TRUST_VERIFIED,
            'trusted' => self::TRUST_TRUSTED,
            default => 0
        };

        $maxPoints = match($level) {
            'verified' => self::TRUST_AUTO_APPROVED - 1,
            'trusted' => self::TRUST_VERIFIED - 1,
            'pending' => self::TRUST_TRUSTED - 1,
            default => null
        };

        $query = UserTrustBalance::where('content_type', $contentType)
            ->where('balance', '>=', $minPoints);

        if ($maxPoints !== null) {
            $query->where('balance', '<=', $maxPoints);
        }

        return User::whereIn('id', $query->pluck('user_id'))
            ->get();
    }

    /**
     * Get trust transaction history for user.
     */
    public function getTransactionHistory(
        User $user,
        ?string $contentType = null,
        ?int $limit = 50
    ): Collection {
        $query = TrustTransaction::where('user_id', $user->id);

        if ($contentType) {
            $query->where('content_type', $contentType);
        }

        return $query->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Get trust statistics for reporting.
     */
    public function getStatistics(?string $contentType = null): array
    {
        $query = UserTrustBalance::query();

        if ($contentType) {
            $query->where('content_type', $contentType);
        }

        return [
            'total_users' => $query->distinct('user_id')->count(),
            'auto_approved_users' => (clone $query)->where('balance', '>=', self::TRUST_AUTO_APPROVED)->count(),
            'verified_users' => (clone $query)->whereBetween('balance', [self::TRUST_VERIFIED, self::TRUST_AUTO_APPROVED - 1])->count(),
            'trusted_users' => (clone $query)->whereBetween('balance', [self::TRUST_TRUSTED, self::TRUST_VERIFIED - 1])->count(),
            'pending_users' => (clone $query)->where('balance', '<', self::TRUST_TRUSTED)->count(),
            'average_trust' => round($query->avg('balance') ?? 0, 2),
            'total_points_awarded' => TrustTransaction::where('points', '>', 0)->sum('points'),
            'total_points_deducted' => abs(TrustTransaction::where('points', '<', 0)->sum('points')),
        ];
    }

    /**
     * Reset user's trust (admin function).
     */
    public function resetTrustPoints(User $user, string $contentType = 'global', string $reason = 'Admin reset', ?User $admin = null): void
    {
        $currentBalance = $this->getBalance($user, $contentType);

        if ($currentBalance != 0) {
            $this->awardPoints(
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
                'admin_id' => $admin?->id
            ]);
        }
    }

    /**
     * Bulk award points for past successful content (migration/backfill).
     */
    public function bulkAwardPastContent(User $user, string $contentType = 'global'): int
    {
        $totalPoints = 0;

        if ($contentType === 'App\\Models\\CommunityEvent' || $contentType === 'global') {
            $successfulEvents = \App\Models\CommunityEvent::where('organizer_id', $user->id)
                ->where('status', \App\Models\CommunityEvent::STATUS_APPROVED)
                ->where('start_time', '<', now())
                ->whereDoesntHave('reports', function($query) {
                    $query->where('status', 'upheld');
                })
                ->count();

            if ($successfulEvents > 0) {
                $points = $successfulEvents * self::POINTS_SUCCESSFUL_CONTENT;
                $this->awardPoints(
                    $user,
                    $points,
                    'App\\Models\\CommunityEvent',
                    'bulk_award',
                    null,
                    "Bulk award for {$successfulEvents} successful events"
                );
                $totalPoints += $points;
            }
        }

        // Add similar logic for other content types as needed

        return $totalPoints;
    }

    /**
     * Get trust level display information.
     */
    public function getTrustLevelInfo(User $user, string $contentType = 'global'): array
    {
        $points = $this->getBalance($user, $contentType);
        $level = $this->getTrustLevel($user, $contentType);

        $info = [
            'level' => $level,
            'points' => $points,
            'content_type' => $contentType,
            'can_auto_approve' => $this->canAutoApprove($user, $contentType),
            'fast_track' => $this->getFastTrackApproval($user, $contentType),
        ];

        // Add progress to next level
        switch ($level) {
            case 'pending':
                $info['next_level'] = 'trusted';
                $info['points_needed'] = self::TRUST_TRUSTED - $points;
                break;
            case 'trusted':
                $info['next_level'] = 'verified';
                $info['points_needed'] = self::TRUST_VERIFIED - $points;
                break;
            case 'verified':
                $info['next_level'] = 'auto-approved';
                $info['points_needed'] = self::TRUST_AUTO_APPROVED - $points;
                break;
            case 'auto-approved':
                $info['next_level'] = null;
                $info['points_needed'] = 0;
                break;
        }

        return $info;
    }

    /**
     * Get trust badge information for display.
     */
    public function getTrustBadge(User $user, string $contentType = 'global'): ?array
    {
        $level = $this->getTrustLevel($user, $contentType);
        $typeName = $this->getContentTypeName($contentType);

        return match($level) {
            'auto-approved' => [
                'label' => "Auto-Approved {$typeName}",
                'color' => 'success',
                'icon' => 'tabler-shield-check',
                'description' => "Content from this user is automatically approved"
            ],
            'verified' => [
                'label' => "Verified {$typeName}",
                'color' => 'info',
                'icon' => 'tabler-shield',
                'description' => "Trusted community member"
            ],
            'trusted' => [
                'label' => "Trusted {$typeName}",
                'color' => 'warning',
                'icon' => 'tabler-star',
                'description' => "Reliable community member"
            ],
            default => null
        };
    }

    /**
     * Determine approval workflow for content.
     */
    public function determineApprovalWorkflow(User $user, string $contentType = 'global'): array
    {
        $trustLevel = $this->getTrustLevel($user, $contentType);

        switch ($trustLevel) {
            case 'auto-approved':
                return [
                    'requires_approval' => false,
                    'auto_publish' => true,
                    'review_priority' => 'none',
                    'estimated_review_time' => 0
                ];

            case 'verified':
            case 'trusted':
                return [
                    'requires_approval' => true,
                    'auto_publish' => false,
                    'review_priority' => 'fast-track',
                    'estimated_review_time' => 24 // hours
                ];

            default:
                return [
                    'requires_approval' => true,
                    'auto_publish' => false,
                    'review_priority' => 'standard',
                    'estimated_review_time' => 72 // hours
                ];
        }
    }

    /**
     * Get human-readable content type name.
     */
    protected function getContentTypeName(string $contentType): string
    {
        return match($contentType) {
            'App\\Models\\CommunityEvent' => 'Event Organizer',
            'App\\Models\\MemberProfile' => 'Profile Creator',
            'App\\Models\\Band' => 'Band Manager',
            'App\\Models\\Production' => 'Production Manager',
            'global' => 'Community Member',
            default => 'Content Creator'
        };
    }

    /**
     * Get content type from model class (returns FQCN).
     */
    protected function getContentTypeFromModel(Model $content): string
    {
        return get_class($content);
    }
}
