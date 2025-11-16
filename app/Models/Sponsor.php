<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Community Sponsor
 *
 * Represents organizations and partners supporting CMC through cash sponsorships
 * or in-kind partnerships. Sponsors receive benefits based on their tier level.
 */
class Sponsor extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'name',
        'tier',
        'type',
        'description',
        'website_url',
        'logo_path',
        'display_order',
        'is_active',
        'started_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'started_at' => 'date',
    ];

    /**
     * Sponsorship tier constants
     */
    const TIER_HARMONY = 'harmony';           // $100/month

    const TIER_MELODY = 'melody';             // $250/month

    const TIER_RHYTHM = 'rhythm';             // $500/month

    const TIER_CRESCENDO = 'crescendo';       // $1000+/month

    const TIER_FUNDRAISING = 'fundraising';   // In-kind: $300+ generated

    const TIER_IN_KIND = 'in_kind';          // In-kind service partner

    /**
     * Type constants
     */
    const TYPE_CASH = 'cash';

    const TYPE_IN_KIND = 'in_kind';

    /**
     * Get all available tiers
     */
    public static function getTiers(): array
    {
        return [
            self::TIER_HARMONY => 'Harmony ($100/month)',
            self::TIER_MELODY => 'Melody ($250/month)',
            self::TIER_RHYTHM => 'Rhythm ($500/month)',
            self::TIER_CRESCENDO => 'Crescendo ($1000+/month)',
            self::TIER_FUNDRAISING => 'Fundraising Partner',
            self::TIER_IN_KIND => 'In-Kind Service Partner',
        ];
    }

    /**
     * Get tier display name
     */
    public function getTierNameAttribute(): string
    {
        return self::getTiers()[$this->tier] ?? $this->tier;
    }

    /**
     * Get monthly sponsored memberships for this tier
     */
    public function getSponsoredMembershipsAttribute(): int
    {
        return match ($this->tier) {
            self::TIER_HARMONY => 5,
            self::TIER_MELODY => 10,
            self::TIER_RHYTHM => 20,
            self::TIER_CRESCENDO => 25,
            self::TIER_FUNDRAISING => 5,
            self::TIER_IN_KIND => 10,
            default => 0,
        };
    }

    /**
     * Check if sponsor has event logo display benefit
     */
    public function hasEventLogoDisplay(): bool
    {
        return in_array($this->tier, [
            self::TIER_MELODY,
            self::TIER_RHYTHM,
            self::TIER_CRESCENDO,
        ]);
    }

    /**
     * Check if sponsor has rehearsal space signage benefit
     */
    public function hasRehearsalSpaceSignage(): bool
    {
        return $this->tier === self::TIER_CRESCENDO;
    }

    /**
     * Check if sponsor has event production discount
     */
    public function hasEventProductionDiscount(): bool
    {
        return $this->tier === self::TIER_CRESCENDO;
    }

    /**
     * Get newsletter recognition level
     */
    public function getNewsletterRecognitionAttribute(): string
    {
        return match ($this->tier) {
            self::TIER_HARMONY, self::TIER_FUNDRAISING, self::TIER_IN_KIND => 'Group supporters list',
            self::TIER_MELODY => 'Group supporters list',
            self::TIER_RHYTHM, self::TIER_CRESCENDO => 'Quarterly sentence',
            default => 'None',
        };
    }

    /**
     * Scope: Active sponsors only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Ordered by display order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('name');
    }

    /**
     * Scope: By tier
     */
    public function scopeByTier($query, string $tier)
    {
        return $query->where('tier', $tier);
    }

    /**
     * Scope: Cash sponsors
     */
    public function scopeCash($query)
    {
        return $query->where('type', self::TYPE_CASH);
    }

    /**
     * Scope: In-kind sponsors
     */
    public function scopeInKind($query)
    {
        return $query->where('type', self::TYPE_IN_KIND);
    }

    /**
     * Scope: Major sponsors (Rhythm and Crescendo tiers)
     */
    public function scopeMajor($query)
    {
        return $query->whereIn('tier', [self::TIER_RHYTHM, self::TIER_CRESCENDO]);
    }

    /**
     * Register media collections
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/svg+xml', 'image/webp']);
    }
}
