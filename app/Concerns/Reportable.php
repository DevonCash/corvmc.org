<?php

namespace App\Concerns;

use App\Models\Report;
use App\Models\User;

trait Reportable
{
    public function reports()
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    public function pendingReports()
    {
        return $this->reports()->where('status', 'pending');
    }

    public function upheldReports()
    {
        return $this->reports()->where('status', 'upheld');
    }

    public function getReportSummary(): array
    {
        return [
            'total' => $this->reports()->count(),
            'pending' => $this->pendingReports()->count(),
            'upheld' => $this->upheldReports()->count(),
            'dismissed' => $this->reports()->where('status', 'dismissed')->count(),
        ];
    }

    public function hasReachedReportThreshold(): bool
    {
        $pendingCount = $this->pendingReports()->count();

        return $pendingCount >= $this->getReportThreshold();
    }

    // Get report threshold - uses static property or default
    public function getReportThreshold(): int
    {
        return static::$reportThreshold ?? 3;
    }

    // Check if content should be auto-hidden when threshold reached
    public function shouldAutoHide(): bool
    {
        return static::$reportAutoHide ?? false;
    }

    // Get human-readable content type name
    public function getReportableType(): string
    {
        return static::$reportableTypeName ?? class_basename(static::class);
    }

    // Check if user has already reported this content
    public function hasBeenReportedBy($user): bool
    {
        return $this->reports()
            ->where('reported_by_id', $user->id)
            ->where('status', 'pending')
            ->exists();
    }

    // Get the most common report reason for this content
    public function getMostCommonReportReason(): ?string
    {
        $mostCommon = $this->reports()
            ->selectRaw('reason, COUNT(*) as count')
            ->groupBy('reason')
            ->orderByDesc('count')
            ->first();

        return $mostCommon?->reason;
    }

    // Trust System Integration

    /**
     * Get the content creator/owner for trust calculations.
     */
    public function getContentCreator(): ?User
    {
        // Try common relationship names
        if (method_exists($this, 'organizer') && $this->organizer) {
            return $this->organizer;
        }

        if (method_exists($this, 'user') && $this->user) {
            return $this->user;
        }

        if (method_exists($this, 'creator') && $this->creator) {
            return $this->creator;
        }

        if (method_exists($this, 'owner') && $this->owner) {
            return $this->owner;
        }

        // Check for direct user_id field
        if (isset($this->user_id)) {
            return User::find($this->user_id);
        }

        return null;
    }

    /**
     * Get the content type for trust system.
     */
    public function getTrustContentType(): string
    {
        return match (class_basename($this)) {
            'CommunityEvent' => 'community_events',
            'MemberProfile' => 'member_profiles',
            'Band' => 'bands',
            'Production' => 'productions',
            default => 'global'
        };
    }

    /**
     * Get trust level information for the content creator.
     */
    public function getCreatorTrustInfo(): ?array
    {
        $creator = $this->getContentCreator();
        if (! $creator) {
            return null;
        }

        return \App\Actions\Trust\GetTrustLevelInfo::run($creator, $this->getTrustContentType());
    }

    /**
     * Get trust badge for the content creator.
     */
    public function getCreatorTrustBadge(): ?array
    {
        $creator = $this->getContentCreator();
        if (! $creator) {
            return null;
        }

        return \App\Actions\Trust\GetTrustBadge::run($creator, $this->getTrustContentType());
    }

    /**
     * Determine approval workflow based on creator's trust level.
     */
    public function getApprovalWorkflow(): array
    {
        $creator = $this->getContentCreator();
        if (! $creator) {
            return [
                'requires_approval' => true,
                'auto_publish' => false,
                'review_priority' => 'standard',
                'estimated_review_time' => 72,
            ];
        }

        return \App\Actions\Trust\DetermineApprovalWorkflow::run($creator, $this->getTrustContentType());
    }

    /**
     * Award trust points to creator for successful content.
     */
    public function awardCreatorTrustPoints(): void
    {
        $creator = $this->getContentCreator();
        if (! $creator) {
            return;
        }

        \App\Actions\Trust\AwardSuccessfulContent::run($creator, $this, $this->getTrustContentType());
    }

    /**
     * Penalize creator for content violations.
     */
    public function penalizeCreatorTrust(string $violationType, string $reason = ''): void
    {
        $creator = $this->getContentCreator();
        if (! $creator) {
            return;
        }

        \App\Actions\Trust\PenalizeViolation::run($creator, $violationType, $this->getTrustContentType(), null, $reason);
    }

    /**
     * Check if content can be auto-approved based on creator trust.
     */
    public function canAutoApprove(): bool
    {
        $creator = $this->getContentCreator();
        if (! $creator) {
            return false;
        }

        return \App\Actions\Trust\CanAutoApprove::run($creator, $this->getTrustContentType());
    }

    /**
     * Check if content gets fast-track approval.
     */
    public function getFastTrackApproval(): bool
    {
        $creator = $this->getContentCreator();
        if (! $creator) {
            return false;
        }

        $points = \App\Actions\Trust\GetTrustBalance::run($creator, $this->getTrustContentType());

        return $points >= \App\Support\TrustConstants::TRUST_TRUSTED;
    }
}
