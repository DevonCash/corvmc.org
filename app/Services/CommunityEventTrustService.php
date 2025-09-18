<?php

namespace App\Services;

use App\Models\User;
use App\Models\CommunityEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * @deprecated Use TrustService instead. This class is maintained for backward compatibility.
 */
class CommunityEventTrustService
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
    const POINTS_SUCCESSFUL_EVENT = 1;
    const POINTS_MINOR_VIOLATION = -3;
    const POINTS_MAJOR_VIOLATION = -5;
    const POINTS_SPAM_VIOLATION = -10;

    /**
     * Cache TTL for trust calculations
     */
    const CACHE_TTL = 3600; // 1 hour

    /**
     * Get the current trust level for a user.
     */
    public function getTrustLevel(User $user): string
    {
        $points = $this->getTrustPoints($user);
        
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
     * Get the trust points for a user.
     */
    public function getTrustPoints(User $user): int
    {
        // Skip caching in test environment to avoid cache issues
        if (app()->environment('testing')) {
            return $user->fresh()->community_event_trust_points ?? 0;
        }
        
        $cacheKey = "community_event_trust_points_{$user->id}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function() use ($user) {
            return $user->community_event_trust_points ?? 0;
        });
    }

    /**
     * Check if a user can auto-approve events.
     */
    public function canAutoApprove(User $user): bool
    {
        return $this->getTrustPoints($user) >= self::TRUST_AUTO_APPROVED;
    }

    /**
     * Check if a user gets fast-track approval (trusted+ users).
     */
    public function getFastTrackApproval(User $user): bool
    {
        return $this->getTrustPoints($user) >= self::TRUST_TRUSTED;
    }

    /**
     * Award trust points for a successful event (no reports).
     */
    public function awardSuccessfulEvent(User $user, CommunityEvent $event): void
    {
        // Only award points for published events that have passed
        if (!$event->isPublished() || $event->isUpcoming()) {
            return;
        }

        // Check if event has any upheld reports
        $hasUpheldReports = $event->reports()
            ->where('status', 'upheld')
            ->exists();

        if (!$hasUpheldReports) {
            $this->adjustTrustPoints($user, self::POINTS_SUCCESSFUL_EVENT, 'Successful event: ' . $event->title);
            
            Log::info('Trust points awarded for successful event', [
                'user_id' => $user->id,
                'event_id' => $event->id,
                'points_awarded' => self::POINTS_SUCCESSFUL_EVENT,
                'new_total' => $this->getTrustPoints($user)
            ]);
        }
    }

    /**
     * Deduct trust points for a content violation.
     */
    public function penalizeViolation(User $user, string $violationType, string $reason = ''): void
    {
        $points = match($violationType) {
            'spam' => self::POINTS_SPAM_VIOLATION,
            'major' => self::POINTS_MAJOR_VIOLATION,
            'minor' => self::POINTS_MINOR_VIOLATION,
            default => self::POINTS_MINOR_VIOLATION,
        };

        $this->adjustTrustPoints($user, $points, "Violation: {$violationType} - {$reason}");
        
        Log::warning('Trust points deducted for violation', [
            'user_id' => $user->id,
            'violation_type' => $violationType,
            'points_deducted' => abs($points),
            'reason' => $reason,
            'new_total' => $this->getTrustPoints($user)
        ]);
    }

    /**
     * Adjust trust points for a user.
     */
    protected function adjustTrustPoints(User $user, int $points, string $reason = ''): void
    {
        $oldPoints = $user->community_event_trust_points ?? 0;
        $newPoints = max(0, $oldPoints + $points); // Don't allow negative points
        
        $user->update(['community_event_trust_points' => $newPoints]);
        
        // Clear cache
        $cacheKey = "community_event_trust_points_{$user->id}";
        Cache::forget($cacheKey);

        // Log the change if it's significant
        if (abs($points) > 0) {
            Log::info('Trust points adjusted', [
                'user_id' => $user->id,
                'old_points' => $oldPoints,
                'new_points' => $newPoints,
                'adjustment' => $points,
                'reason' => $reason
            ]);
        }
    }

    /**
     * Get trust level display information.
     */
    public function getTrustLevelInfo(User $user): array
    {
        $points = $this->getTrustPoints($user);
        $level = $this->getTrustLevel($user);
        
        $info = [
            'level' => $level,
            'points' => $points,
            'can_auto_approve' => $this->canAutoApprove($user),
            'fast_track' => $this->getFastTrackApproval($user),
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
    public function getTrustBadge(User $user): ?array
    {
        $level = $this->getTrustLevel($user);
        
        return match($level) {
            'auto-approved' => [
                'label' => 'Auto-Approved Organizer',
                'color' => 'success',
                'icon' => 'tabler-shield-check',
                'description' => 'Events from this organizer are automatically approved'
            ],
            'verified' => [
                'label' => 'Verified Organizer',
                'color' => 'info', 
                'icon' => 'tabler-shield',
                'description' => 'Trusted community event organizer'
            ],
            'trusted' => [
                'label' => 'Trusted Organizer',
                'color' => 'warning',
                'icon' => 'tabler-star',
                'description' => 'Reliable event organizer'
            ],
            default => null
        };
    }

    /**
     * Determine approval workflow for an event.
     */
    public function determineApprovalWorkflow(User $organizer, CommunityEvent $event): array
    {
        $trustLevel = $this->getTrustLevel($organizer);
        
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
     * Bulk award points for past successful events.
     * Useful for initial trust calculation or periodic reviews.
     */
    public function bulkAwardPastEvents(User $user): int
    {
        $successfulEvents = CommunityEvent::where('organizer_id', $user->id)
            ->where('status', CommunityEvent::STATUS_APPROVED)
            ->where('start_time', '<', now())
            ->whereDoesntHave('reports', function($query) {
                $query->where('status', 'upheld');
            })
            ->count();

        if ($successfulEvents > 0) {
            $totalPoints = $successfulEvents * self::POINTS_SUCCESSFUL_EVENT;
            $this->adjustTrustPoints($user, $totalPoints, "Bulk award for {$successfulEvents} successful past events");
            
            return $totalPoints;
        }

        return 0;
    }

    /**
     * Get users by trust level for admin interface.
     */
    public function getUsersByTrustLevel(string $level): \Illuminate\Database\Eloquent\Collection
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

        $query = User::where('community_event_trust_points', '>=', $minPoints);
        
        if ($maxPoints !== null) {
            $query->where('community_event_trust_points', '<=', $maxPoints);
        }

        return $query->orderBy('community_event_trust_points', 'desc')->get();
    }

    /**
     * Reset trust points for a user (admin function).
     */
    public function resetTrustPoints(User $user, string $reason = 'Admin reset'): void
    {
        $oldPoints = $user->community_event_trust_points ?? 0;
        $user->update(['community_event_trust_points' => 0]);
        
        // Clear cache
        $cacheKey = "community_event_trust_points_{$user->id}";
        Cache::forget($cacheKey);

        Log::warning('Trust points reset by admin', [
            'user_id' => $user->id,
            'old_points' => $oldPoints,
            'reason' => $reason
        ]);
    }
}