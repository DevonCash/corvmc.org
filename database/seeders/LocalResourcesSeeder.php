<?php

namespace Database\Seeders;

use App\Models\LocalResource;
use App\Models\ResourceList;
use Illuminate\Database\Seeder;

class LocalResourcesSeeder extends Seeder
{
    /**
     * Seed local resources with a realistic spread of data for verifying
     * the public display. Includes published, draft, and scheduled resources
     * across several categories, with varying field population.
     */
    public function run(): void
    {
        // Music Shops — all published, most fields populated
        $musicShops = ResourceList::create([
            'name' => 'Music Shops',
            'description' => 'Local stores for instruments, gear, and accessories.',
            'display_order' => 1,
        ]);

        $this->createResources($musicShops, [
            [
                'name' => 'Gracewinds Music',
                'description' => 'Instruments, lessons, and repairs',
                'website' => 'https://gracewinds.com',
                'address' => '137 SW 3rd St, Corvallis, OR',
            ],
            [
                'name' => 'Guitar Center',
                'website' => 'https://guitarcenter.com',
                'address' => '1845 NW 9th St, Corvallis, OR',
            ],
            [
                'name' => 'Sid Stevens Music',
                'description' => 'Band and orchestra instruments',
                'website' => 'https://sidstevensmusic.com',
                'address' => '2600 NW 9th St, Corvallis, OR',
            ],
        ]);

        // Recording Studios — mix of published and draft
        $studios = ResourceList::create([
            'name' => 'Recording Studios',
            'description' => 'Professional recording facilities in the Corvallis area.',
            'display_order' => 2,
        ]);

        $this->createResources($studios, [
            [
                'name' => 'Gopher Broke Studios',
                'description' => 'Recording, mixing, and mastering',
                'website' => 'https://gopherbrokestudios.com',
                'address' => 'Corvallis, OR',
            ],
            [
                'name' => 'Falcon Recording Studio',
                'description' => 'Analog and digital recording with vintage gear',
                'published_at' => null, // Draft
            ],
        ]);

        // Jams & Open Mics — uses "note" style descriptions
        $jams = ResourceList::create([
            'name' => 'Jams & Open Mics',
            'description' => 'Recurring community jams and open mic nights.',
            'display_order' => 3,
        ]);

        $this->createResources($jams, [
            [
                'name' => 'Corvallis Community Jam',
                'description' => 'Third Thursday at Common Fields',
                'address' => '545 SW 3rd St, Corvallis, OR',
            ],
            [
                'name' => 'Open Mic at Bombs Away',
                'description' => 'Every Monday, sign-up at 7pm',
                'website' => 'https://bombsawaycafe.com',
                'address' => '2527 NW Monroe Ave, Corvallis, OR',
            ],
            [
                'name' => 'Old World Deli Jazz Jam',
                'description' => 'First and third Sunday afternoons',
                'address' => '341 SW 2nd St, Corvallis, OR',
            ],
            [
                'name' => 'Acoustic Showcase',
                'description' => 'Second Friday — hosted by CorvMC members',
                'published_at' => now()->addDays(3), // Scheduled
            ],
        ]);

        // Instrument Repair
        $repair = ResourceList::create([
            'name' => 'Instrument Repair',
            'description' => 'Trusted repair services for guitars, brass, woodwinds, and more.',
            'display_order' => 4,
        ]);

        $this->createResources($repair, [
            [
                'name' => 'Gracewinds Repair Shop',
                'website' => 'https://gracewinds.com/repairs',
                'address' => '137 SW 3rd St, Corvallis, OR',
            ],
            [
                'name' => 'Valley Guitar Repair',
                'description' => 'Acoustic and electric setups',
            ],
            [
                'name' => 'Brass & Reed Works',
                'description' => 'Band instrument repair and restoration',
                'published_at' => null, // Draft
            ],
        ]);

        // Music Teachers — smaller category, one draft
        $teachers = ResourceList::create([
            'name' => 'Music Teachers',
            'description' => 'Private lesson instructors for various instruments and voice.',
            'display_order' => 5,
        ]);

        $this->createResources($teachers, [
            [
                'name' => 'Corvallis Guitar Lessons',
                'description' => 'All ages and skill levels',
                'website' => 'https://corvallisguitarlessons.com',
            ],
            [
                'name' => 'Willamette Valley Voice Studio',
                'description' => 'Classical and contemporary voice training',
            ],
            [
                'name' => 'Drum Dynamics',
                'website' => 'https://drumdynamics.net',
                'published_at' => null, // Draft
            ],
        ]);

        // Merch & Design — small, all published
        $merch = ResourceList::create([
            'name' => 'Merch & Design',
            'description' => 'Band merchandise, posters, and apparel.',
            'display_order' => 6,
        ]);

        $this->createResources($merch, [
            [
                'name' => 'Inkwell Press',
                'description' => 'Screen printing and poster design',
                'website' => 'https://inkwellpress.co',
                'address' => 'Albany, OR',
            ],
            [
                'name' => 'Valley Custom Apparel',
                'website' => 'https://valleycustomapparel.com',
            ],
        ]);

        // Venues — entirely draft category (no published resources, won't show on public page)
        $venues = ResourceList::create([
            'name' => 'Venues',
            'description' => 'Local venues that host live music.',
            'display_order' => 7,
        ]);

        $this->createResources($venues, [
            [
                'name' => 'The Whiteside Theatre',
                'website' => 'https://whitesidetheatre.org',
                'address' => '361 SW Madison Ave, Corvallis, OR',
                'published_at' => null, // Draft
            ],
            [
                'name' => 'Bombs Away Café',
                'website' => 'https://bombsawaycafe.com',
                'address' => '2527 NW Monroe Ave, Corvallis, OR',
                'published_at' => null, // Draft
            ],
        ]);

        $published = LocalResource::whereNotNull('published_at')->where('published_at', '<=', now())->count();
        $drafts = LocalResource::whereNull('published_at')->count();
        $scheduled = LocalResource::whereNotNull('published_at')->where('published_at', '>', now())->count();

        $this->command->info("Seeded {$published} published, {$drafts} draft, {$scheduled} scheduled resources across " . ResourceList::count() . ' categories.');
    }

    private function createResources(ResourceList $list, array $resources): void
    {
        foreach ($resources as $index => $data) {
            LocalResource::create([
                'resource_list_id' => $list->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'website' => $data['website'] ?? null,
                'address' => $data['address'] ?? null,
                'published_at' => array_key_exists('published_at', $data) ? $data['published_at'] : now(),
                'sort_order' => $index + 1,
            ]);
        }
    }
}
