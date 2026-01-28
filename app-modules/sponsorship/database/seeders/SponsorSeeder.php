<?php

namespace CorvMC\Sponsorship\Database\Seeders;

use CorvMC\Sponsorship\Models\Sponsor;
use App\Models\User;
use Illuminate\Database\Seeder;

class SponsorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Skip logo downloads by default to save memory and time
        // Set SEED_SPONSOR_LOGOS=true to download placeholder logos
        $downloadLogos = env('SEED_SPONSOR_LOGOS', false);

        $logoCounter = 1;

        // Helper to add logo from placeholder service (only if enabled)
        $addLogo = function ($sponsor) use (&$logoCounter, $downloadLogos) {
            if (!$downloadLogos) {
                $logoCounter++;
                return;
            }

            try {
                // Using picsum.photos for placeholder images (300x300 squares)
                $logoUrl = "https://picsum.photos/seed/sponsor{$logoCounter}/300/300";
                $sponsor->addMediaFromUrl($logoUrl)->toMediaCollection('logo');
                $logoCounter++;
            } catch (\Exception $e) {
                // Skip if logo download fails
                $logoCounter++;
            }
        };

        // Crescendo Tier ($1000+/month) - 2 sponsors
        $sponsor = Sponsor::factory()->crescendo()->active()->create([
            'name' => 'Valley Community Bank',
            'description' => 'A community-focused financial institution supporting local artists and musicians through grants and sponsorships. Building stronger communities through music education.',
            'website_url' => 'https://example.com',
            'display_order' => 1,
        ]);
        $addLogo($sponsor);

        $sponsor = Sponsor::factory()->crescendo()->active()->create([
            'name' => 'Riverside Credit Union',
            'description' => 'Member-owned financial cooperative dedicated to supporting community arts programs and local music initiatives throughout the region.',
            'website_url' => 'https://example.com',
            'display_order' => 2,
        ]);
        $addLogo($sponsor);

        // Rhythm Tier ($500/month) - 3 sponsors
        $sponsor = Sponsor::factory()->rhythm()->active()->create([
            'name' => 'Mountain View Coffee Roasters',
            'description' => 'Independent coffee roaster committed to sustainability and supporting local arts.',
            'website_url' => 'https://example.com',
            'display_order' => 11,
        ]);
        $addLogo($sponsor);

        $sponsor = Sponsor::factory()->rhythm()->active()->create([
            'name' => 'Oakwood Brewing Company',
            'description' => 'Craft brewery supporting local music and cultural events in the community.',
            'website_url' => 'https://example.com',
            'display_order' => 12,
        ]);
        $addLogo($sponsor);

        $sponsor = Sponsor::factory()->rhythm()->active()->create([
            'name' => 'Harmony Books & Records',
            'description' => 'Independent bookstore and music shop celebrating local artists and authors.',
            'website_url' => 'https://example.com',
            'display_order' => 13,
        ]);
        $addLogo($sponsor);

        // Melody Tier ($250/month) - 4 sponsors
        $sponsor = Sponsor::factory()->melody()->active()->create([
            'name' => 'The Corner Tap House',
            'website_url' => 'https://example.com',
            'display_order' => 31,
        ]);
        $addLogo($sponsor);

        $sponsor = Sponsor::factory()->melody()->active()->create([
            'name' => 'Sunrise Cafe',
            'website_url' => 'https://example.com',
            'display_order' => 32,
        ]);
        $addLogo($sponsor);

        $sponsor = Sponsor::factory()->melody()->active()->create([
            'name' => 'Historic Plaza Theatre',
            'website_url' => 'https://example.com',
            'display_order' => 33,
        ]);
        $addLogo($sponsor);

        $sponsor = Sponsor::factory()->melody()->active()->create([
            'name' => 'Pacific Rim Bistro',
            'display_order' => 34,
        ]);
        $addLogo($sponsor);

        // Harmony Tier ($100/month) - 6 sponsors
        $harmonySponsors = [
            'Early Bird Bakery',
            'Artisan Pizza Co',
            'The Red Door Pub',
            'Riverside Cafe',
            'Downtown Coffee Lab',
            'Main Street Roasters',
        ];

        foreach ($harmonySponsors as $index => $name) {
            $sponsor = Sponsor::factory()->harmony()->active()->create([
                'name' => $name,
                'display_order' => 61 + $index,
            ]);
            $addLogo($sponsor);
        }

        // In-Kind Partners - 3 sponsors
        $sponsor = Sponsor::factory()->inKind()->active()->create([
            'name' => 'Summit Outdoors',
            'description' => 'Outdoor gear retailer providing equipment storage solutions.',
            'display_order' => 96,
        ]);
        $addLogo($sponsor);

        $sponsor = Sponsor::factory()->fundraising()->active()->create([
            'name' => 'Community Arts Alliance',
            'description' => 'Partnering to promote local arts and music events.',
            'website_url' => 'https://example.com',
            'display_order' => 97,
        ]);
        $addLogo($sponsor);

        $sponsor = Sponsor::factory()->inKind()->active()->create([
            'name' => 'PrintPro Services',
            'description' => 'Providing printing services for promotional materials and event flyers.',
            'display_order' => 98,
        ]);
        $addLogo($sponsor);

        // Add a few inactive sponsors to test filtering
        Sponsor::factory()->harmony()->inactive()->create([
            'name' => 'Former Sponsor LLC',
            'display_order' => 99,
        ]);

        // Add example sponsored memberships
        $this->assignSponsoredMemberships();
    }

    /**
     * Assign example sponsored memberships to demonstrate the feature
     */
    private function assignSponsoredMemberships(): void
    {
        // Get some sponsors
        $crescendoSponsor = Sponsor::where('tier', Sponsor::TIER_CRESCENDO)->first();
        $rhythmSponsor = Sponsor::where('tier', Sponsor::TIER_RHYTHM)->first();
        $melodySponsor = Sponsor::where('tier', Sponsor::TIER_MELODY)->first();
        $harmonySponsor = Sponsor::where('tier', Sponsor::TIER_HARMONY)->first();

        // Get some users (if they exist)
        $users = User::limit(10)->get();

        if ($users->isEmpty()) {
            // If no users exist, skip sponsored membership assignments
            return;
        }

        // Assign sponsored memberships to demonstrate different usage levels
        if ($crescendoSponsor && $users->count() >= 5) {
            // Crescendo sponsor sponsors 5 members (25 total available)
            foreach ($users->slice(0, 5) as $user) {
                $crescendoSponsor->sponsoredMembers()->attach($user->id);
            }
        }

        if ($rhythmSponsor && $users->count() >= 8) {
            // Rhythm sponsor sponsors 8 members (20 total available)
            foreach ($users->slice(0, 8) as $user) {
                $rhythmSponsor->sponsoredMembers()->attach($user->id);
            }
        }

        if ($melodySponsor && $users->count() >= 10) {
            // Melody sponsor is at capacity (10 total available)
            foreach ($users->slice(0, 10) as $user) {
                $melodySponsor->sponsoredMembers()->attach($user->id);
            }
        }

        if ($harmonySponsor && $users->count() >= 3) {
            // Harmony sponsor sponsors 3 members (5 total available)
            foreach ($users->slice(0, 3) as $user) {
                $harmonySponsor->sponsoredMembers()->attach($user->id);
            }
        }
    }
}
