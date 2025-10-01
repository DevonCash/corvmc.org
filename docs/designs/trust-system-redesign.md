# Trust/Reputation System Redesign

**Status:** Design Proposal
**Created:** October 1, 2025
**Purpose:** Replace JSON-based trust points with transaction-safe, auditable reputation system

## Problem Statement

Current trust system has several issues:

- **No audit trail**: Can't see who adjusted points, when, or why
- **JSON storage inefficiency**: Can't index or query trust points efficiently
- **No transaction safety**: Concurrent adjustments could cause race conditions
- **Cache complexity**: Same issues as free hours system (stale cache, invalidation)
- **Poor queryability**: Can't easily find "all verified users" or "users with 10+ trust points"
- **Two implementations**: `TrustService` (new, JSON) and `CommunityEventTrustService` (deprecated, column) coexist
- **No reporting**: Hard to analyze trust distribution, point allocation patterns, or violation trends

## Current Implementation

### Data Storage
```php
// users table
trust_points: JSON {
    "global": 15,
    "App\\Models\\CommunityEvent": 12,
    "App\\Models\\MemberProfile": 8,
    "App\\Models\\Band": 5,
    "App\\Models\\Production": 0
}
community_event_trust_points: INTEGER (deprecated)
```

### Trust Levels
- **Pending**: 0-4 points (standard approval, 72hr review)
- **Trusted**: 5-14 points (fast-track approval, 24hr review)
- **Verified**: 15-29 points (fast-track approval, visible badge)
- **Auto-Approved**: 30+ points (no approval needed)

### Point Awards/Penalties
- **Successful content**: +1 point (content published without upheld reports)
- **Minor violation**: -3 points
- **Major violation**: -5 points
- **Spam violation**: -10 points

## Proposed Solution: Trust Transactions System

### Core Concept
Replace JSON storage with dedicated tables for trust balances and full transaction history.

### Database Schema

```sql
-- User trust balances (replaces JSON field)
CREATE TABLE user_trust_balances (
    id BIGINT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    content_type VARCHAR(100) NOT NULL, -- FQCN or 'global'
    balance INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    UNIQUE(user_id, content_type),
    INDEX(user_id),
    INDEX(content_type, balance) -- For querying by trust level
);

-- Trust transactions (complete audit trail)
CREATE TABLE trust_transactions (
    id BIGINT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    content_type VARCHAR(100) NOT NULL,
    points INTEGER NOT NULL, -- Positive = award, Negative = penalty
    balance_after INTEGER NOT NULL,
    reason VARCHAR(255) NOT NULL,
    source_type VARCHAR(50) NOT NULL, -- 'successful_content', 'minor_violation', 'major_violation', 'spam_violation', 'admin_adjustment', 'bulk_award', 'reset'
    source_id BIGINT NULL, -- ID of related model (CommunityEvent, Report, etc.)
    awarded_by_id BIGINT NULL, -- Admin user who made adjustment (if manual)
    metadata JSON NULL, -- Additional context
    created_at TIMESTAMP,

    INDEX(user_id, created_at),
    INDEX(content_type),
    INDEX(source_type, source_id)
);

-- Trust level achievements (optional: track when users reach milestones)
CREATE TABLE trust_achievements (
    id BIGINT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    content_type VARCHAR(100) NOT NULL,
    level VARCHAR(20) NOT NULL, -- 'trusted', 'verified', 'auto_approved'
    achieved_at TIMESTAMP,

    UNIQUE(user_id, content_type, level),
    INDEX(user_id),
    INDEX(achieved_at)
);
```

### Service Layer

```php
class TrustService
{
    // Trust level thresholds (unchanged)
    const TRUST_TRUSTED = 5;
    const TRUST_VERIFIED = 15;
    const TRUST_AUTO_APPROVED = 30;

    // Point values (unchanged)
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
            $newBalance = max(0, $oldBalance + $points); // Don't allow negative for content types

            // Special case: global trust can go negative (users in poor standing)
            if ($contentType === 'global') {
                $newBalance = $oldBalance + $points;
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
    public function awardSuccessfulContent(User $user, Model $content): void
    {
        $contentType = get_class($content);

        // Only award if content should be evaluated
        if (!$this->shouldEvaluateContent($content)) {
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
        if (class_exists($contentType) && in_array(\App\Traits\Revisionable::class, class_uses_recursive($contentType))) {
            $tempInstance = new $contentType();
            if ($tempInstance->getAutoApproveMode() === 'never') {
                return false;
            }
        }

        return $this->getBalance($user, $contentType) >= self::TRUST_AUTO_APPROVED;
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
            'auto_approved_users' => $query->where('balance', '>=', self::TRUST_AUTO_APPROVED)->count(),
            'verified_users' => $query->whereBetween('balance', [self::TRUST_VERIFIED, self::TRUST_AUTO_APPROVED - 1])->count(),
            'trusted_users' => $query->whereBetween('balance', [self::TRUST_TRUSTED, self::TRUST_VERIFIED - 1])->count(),
            'pending_users' => $query->where('balance', '<', self::TRUST_TRUSTED)->count(),
            'average_trust' => $query->avg('balance'),
            'total_points_awarded' => TrustTransaction::where('points', '>', 0)->sum('points'),
            'total_points_deducted' => TrustTransaction::where('points', '<', 0)->sum('points'),
        ];
    }

    /**
     * Reset user's trust (admin function).
     */
    public function resetTrust(User $user, string $contentType, string $reason, User $admin): void
    {
        $this->awardPoints(
            $user,
            -$this->getBalance($user, $contentType), // Negative of current balance
            $contentType,
            'reset',
            null,
            "Admin reset: {$reason}",
            $admin
        );
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
}
```

## Migration Strategy

**Pre-deployment**: No data migration needed since application isn't in production yet.

### Implementation Plan

1. **Create new tables**
   - Run migrations for `user_trust_balances`, `trust_transactions`, `trust_achievements`

2. **Update TrustService**
   - Replace JSON reads with table queries
   - Remove cache layer (no longer needed)
   - Add transaction safety
   - Keep same public API for backward compatibility

3. **Remove old system**
   - Delete `CommunityEventTrustService` (deprecated)
   - Drop `users.trust_points` JSON column in migration
   - Drop `users.community_event_trust_points` column in migration
   - Update all references to use new TrustService

4. **Update tests**
   - Rewrite trust tests to check database tables
   - Test transaction safety
   - Test achievement tracking
   - Test all content types (CommunityEvent, MemberProfile, Band, Production)

5. **Deploy**
   - Single deployment with new trust system
   - No feature flags needed
   - No backward compatibility concerns

## Benefits

### Immediate
- ✅ **Full audit trail**: See every trust point change with reason
- ✅ **Transaction-safe**: Database locks prevent race conditions
- ✅ **No cache issues**: Fresh data from indexed queries
- ✅ **Query-friendly**: Efficient lookups by trust level
- ✅ **Better reporting**: Analytics on trust distribution and patterns

### Long-term
- 📊 **Trust analytics**: Track which content types earn most trust
- 👥 **Admin transparency**: Users can see why they gained/lost points
- 🎯 **Gamification**: Show achievements, progress to next level
- 📈 **Trending**: Identify rising trusted contributors
- 🔧 **Support tools**: Admins can easily adjust trust with audit trail
- 📧 **Notifications**: Alert users when they reach new trust levels

## Technical Debt Addressed

- ❌ Remove JSON storage and inefficient queries
- ❌ Remove cache invalidation complexity
- ❌ Delete deprecated `CommunityEventTrustService`
- ❌ Remove `community_event_trust_points` column
- ✅ Add proper transaction locking
- ✅ Add comprehensive audit logging
- ✅ Add achievement tracking

## Design Decisions

1. **Integer Storage**: Trust points stored as integers
   - Simple, no fractional points needed
   - Easy to query and index

2. **Negative Balances**: Only for global trust
   - Content-type trust: minimum 0 (can't go negative)
   - Global trust: can go negative (users in poor standing)
   - Rationale: Content-specific trust resets per type, global reflects overall standing

3. **Global Trust Calculation**: Weighted average
   - Average of all content types with points > 0
   - Updated whenever any content-type trust changes
   - Separate record in `user_trust_balances` for easy querying

4. **Achievement Tracking**: Optional but valuable
   - Records when users cross thresholds
   - Useful for notifications and gamification
   - Low overhead (3 records max per user per content type)

5. **Transaction History**: Unlimited retention
   - Keep all transactions indefinitely for audit
   - Could archive after 2 years if needed
   - Indexed by user_id + created_at for performance

## Open Questions

1. **Trust decay**: Should trust points decay over time if inactive?
   - Proposal: No decay for initial implementation, add later if needed

2. **Transfer between types**: Can trust in one area boost another?
   - Current: Independent per content type + global average
   - Future: Could add "trust transfer" for related types

3. **Public visibility**: Should users see their trust level/points?
   - Proposal: Yes, show current level and points needed for next level
   - Hide specific transaction details to avoid gaming the system

## Estimated Effort

- **Database migrations**: 1 hour
  - Create 3 new tables
  - Drop old JSON columns

- **Models**: 1 hour
  - UserTrustBalance, TrustTransaction, TrustAchievement models
  - Relationships and scopes

- **TrustService rewrite**: 3-4 hours
  - Update all methods to use tables
  - Add transaction safety
  - Remove cache layer

- **Remove deprecated code**: 1-2 hours
  - Delete CommunityEventTrustService
  - Update all references throughout codebase

- **Testing**: 2-3 hours
  - Update trust system tests
  - Test transaction safety
  - Test achievement tracking

**Total: 8-11 hours**

## Success Metrics

- Zero cache-related bugs in trust calculations
- 100% transaction success rate (no race conditions)
- Sub-50ms trust balance lookups (indexed queries)
- Full audit trail for all trust changes
- Efficient "get all verified users" queries
- Support for trust analytics and reporting

## References

- Laravel Database Transactions: https://laravel.com/docs/database#database-transactions
- Reputation System Design Patterns
- Credits System Design (similar approach): `docs/designs/credits-system-design.md`
