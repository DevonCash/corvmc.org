<?php

namespace App\Actions\Sponsors;

use App\Models\Sponsor;
use Lorisleiva\Actions\Concerns\AsAction;

class GetSponsorBenefitsSummary
{
    use AsAction;

    /**
     * Get sponsor benefits summary for a given tier
     */
    public function handle(string $tier): array
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

            case Sponsor::TYPE_IN_KIND:
                $benefits['sponsored_memberships'] = 10;
                break;
        }

        return $benefits;
    }
}
