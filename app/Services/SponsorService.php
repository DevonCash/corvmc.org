<?php

namespace App\Services;

use App\Models\Sponsor;
use Illuminate\Support\Facades\Cache;

/**
 * Sponsor Service
 *
 * Handles business logic for sponsor management and display.
 */
class SponsorService
{
    /**
     * Get all active sponsors grouped by tier
     */
    public function getActiveSponsors(): array
    {
        return Cache::remember('sponsors.active.grouped', 3600, function () {
            $sponsors = Sponsor::active()->ordered()->get();

            return [
                'crescendo' => $sponsors->where('tier', Sponsor::TIER_CRESCENDO),
                'rhythm' => $sponsors->where('tier', Sponsor::TIER_RHYTHM),
                'melody' => $sponsors->where('tier', Sponsor::TIER_MELODY),
                'harmony' => $sponsors->where('tier', Sponsor::TIER_HARMONY),
                'in_kind' => $sponsors->where('type', Sponsor::TYPE_IN_KIND),
            ];
        });
    }

    /**
     * Get major sponsors (Rhythm and Crescendo tiers) for home page display
     */
    public function getMajorSponsors()
    {
        return Cache::remember('sponsors.major', 3600, function () {
            return Sponsor::active()->major()->ordered()->get();
        });
    }

    /**
     * Get sponsors eligible for event logo display
     */
    public function getEventLogoSponsors()
    {
        return Cache::remember('sponsors.event_logo', 3600, function () {
            return Sponsor::active()
                ->whereIn('tier', [
                    Sponsor::TIER_MELODY,
                    Sponsor::TIER_RHYTHM,
                    Sponsor::TIER_CRESCENDO
                ])
                ->ordered()
                ->get();
        });
    }

    /**
     * Clear all sponsor caches
     */
    public static function clearCaches(): void
    {
        Cache::forget('sponsors.active.grouped');
        Cache::forget('sponsors.major');
        Cache::forget('sponsors.event_logo');
    }

    /**
     * Get sponsor benefits summary for a given tier
     */
    public function getBenefitsSummary(string $tier): array
    {
        $benefits = [
            'website_logo' => true,
            'newsletter_listing' => 'Group supporters list',
            'event_logo_display' => false,
            'newsletter_feature' => false,
            'rehearsal_space_signage' => false,
            'event_production_discount' => false,
            'sponsored_memberships' => 0,
        ];

        switch ($tier) {
            case Sponsor::TIER_CRESCENDO:
                $benefits['newsletter_feature'] = 'Quarterly sentence';
                $benefits['event_logo_display'] = true;
                $benefits['rehearsal_space_signage'] = true;
                $benefits['event_production_discount'] = '50% off sound services';
                $benefits['sponsored_memberships'] = 25;
                break;

            case Sponsor::TIER_RHYTHM:
                $benefits['newsletter_feature'] = 'Quarterly sentence';
                $benefits['event_logo_display'] = true;
                $benefits['sponsored_memberships'] = 20;
                break;

            case Sponsor::TIER_MELODY:
                $benefits['event_logo_display'] = true;
                $benefits['sponsored_memberships'] = 10;
                break;

            case Sponsor::TIER_HARMONY:
                $benefits['sponsored_memberships'] = 5;
                break;

            case Sponsor::TIER_FUNDRAISING:
                $benefits['sponsored_memberships'] = 5;
                break;

            case Sponsor::TIER_IN_KIND:
                $benefits['sponsored_memberships'] = 10;
                break;
        }

        return $benefits;
    }
}
