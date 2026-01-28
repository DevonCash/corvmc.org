<?php

namespace CorvMC\Moderation\Enums;

use App\Support\TrustConstants;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum TrustLevel: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    case Pending = 'pending';
    case Trusted = 'trusted';
    case Verified = 'verified';
    case AutoApproved = 'auto-approved';

    /**
     * Create TrustLevel from points.
     */
    public static function fromPoints(int $points): self
    {
        $thresholds = config('moderation.thresholds', [
            'trusted' => 5,
            'verified' => 15,
            'auto_approved' => 30,
        ]);
        if ($points >= $thresholds['auto_approved']) {
            return self::AutoApproved;
        } elseif ($points >= $thresholds['verified']) {
            return self::Verified;
        } elseif ($points >= $thresholds['trusted']) {
            return self::Trusted;
        }

        return self::Pending;
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Trusted => 'Trusted',
            self::Verified => 'Verified',
            self::AutoApproved => 'Auto-Approved',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Pending => 'New or unproven community member',
            self::Trusted => 'Reliable community member',
            self::Verified => 'Trusted community member',
            self::AutoApproved => 'Content from this user is automatically approved',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Pending => 'tabler-hourglass',
            self::Trusted => 'tabler-star',
            self::Verified => 'tabler-shield',
            self::AutoApproved => 'tabler-shield-check',
        };
    }

    public function getColor(): string|array
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Trusted => 'warning',
            self::Verified => 'info',
            self::AutoApproved => 'success',
        };
    }

    /**
     * Get the minimum points threshold for this level.
     */
    public function getThreshold(): int
    {
        return match ($this) {
            self::Pending => 0,
            self::Trusted => config('moderation.thresholds.trusted'),
            self::Verified => config('moderation.thresholds.verified'),
            self::AutoApproved => config('moderation.thresholds.auto_approved'),
        };
    }

    /**
     * Get the next trust level.
     */
    public function getNextLevel(): ?self
    {
        return match ($this) {
            self::Pending => self::Trusted,
            self::Trusted => self::Verified,
            self::Verified => self::AutoApproved,
            self::AutoApproved => null,
        };
    }

    /**
     * Get points needed to reach the next level.
     */
    public function getPointsNeededForNext(int $currentPoints): int
    {
        $nextLevel = $this->getNextLevel();

        if ($nextLevel === null) {
            return 0;
        }

        return max(0, $nextLevel->getThreshold() - $currentPoints);
    }

    /**
     * Get estimated review time in hours.
     */
    public function getEstimatedReviewTime(): int
    {
        return match ($this) {
            self::Pending => 72,
            self::Trusted, self::Verified => 24,
            self::AutoApproved => 0,
        };
    }

    /**
     * Get review priority level.
     */
    public function getReviewPriority(): string
    {
        return match ($this) {
            self::Pending => 'standard',
            self::Trusted, self::Verified => 'fast-track',
            self::AutoApproved => 'none',
        };
    }

    /**
     * Check if this level allows auto-approval.
     */
    public function canAutoApprove(): bool
    {
        return $this === self::AutoApproved;
    }

    /**
     * Check if this level gets fast-track review.
     */
    public function isFastTrack(): bool
    {
        return in_array($this, [self::Trusted, self::Verified, self::AutoApproved]);
    }

    /**
     * Check if user needs manual review.
     */
    public function requiresReview(): bool
    {
        return $this !== self::AutoApproved;
    }

    /**
     * Get badge information for display.
     */
    public function getBadgeInfo(string $contentTypeName = 'Community Member'): ?array
    {
        // Only show badges for Trusted and above
        if ($this === self::Pending) {
            return null;
        }

        return [
            'label' => $this->getLabel().' '.$contentTypeName,
            'color' => $this->getColor(),
            'icon' => $this->getIcon(),
            'description' => $this->getDescription(),
        ];
    }
}
