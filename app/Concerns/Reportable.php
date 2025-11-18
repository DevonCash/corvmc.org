<?php

namespace App\Concerns;

use App\Models\Report;
use App\Models\User;

trait Reportable
{
    public function reports(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    public function pendingReports(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->reports()->where('status', 'pending');
    }

    public function upheldReports(): \Illuminate\Database\Eloquent\Relations\MorphMany
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
     * The foreign key field name for the content creator.
     * Override in child models if needed.
     */
    protected static string $creatorForeignKey = 'user_id';

    /**
     * Get the content creator/owner for trust calculations.
     */
    public function getContentCreator(): ?User
    {
        $foreignKey = static::$creatorForeignKey;

        // Get the relationship method name from the foreign key
        // e.g., 'user_id' -> 'user', 'organizer_id' -> 'organizer'
        $relationshipName = str_replace('_id', '', $foreignKey);

        // Try to use the relationship method if it exists
        if (method_exists($this, $relationshipName)) {
            return $this->{$relationshipName};
        }

        // Fall back to direct foreign key access
        if (isset($this->{$foreignKey})) {
            return User::find($this->{$foreignKey});
        }

        return null;
    }

    /**
     * Get the content type for trust system.
     */
    public function getTrustContentType(): string
    {
        return match (class_basename($this)) {
            'Event' => 'events',
            'MemberProfile' => 'member_profiles',
            'Band' => 'bands',
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

        return $creator->getTrustLevelInfo($this->getTrustContentType());
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

        return $creator->getTrustBadge($this->getTrustContentType());
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

        return $creator->canAutoApprove($this->getTrustContentType());
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

        $points = $creator->getTrustBalance($this->getTrustContentType());

        return $points >= \App\Support\TrustConstants::TRUST_TRUSTED;
    }
}
