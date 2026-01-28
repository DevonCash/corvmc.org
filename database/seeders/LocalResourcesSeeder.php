<?php

namespace Database\Seeders;

use App\Models\LocalResource;
use App\Models\ResourceList;
use Illuminate\Database\Seeder;

class LocalResourcesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Music Shops
        $musicShops = ResourceList::create([
            'name' => 'Music Shops',
            'slug' => 'music-shops',
            'description' => 'Local stores for instruments, gear, and accessories.',
            'published_at' => now(),
            'display_order' => 1,
        ]);

        $this->createResources($musicShops, [
            [
                'name' => 'Gracewinds Music',
                'description' => 'Full-service music store with instruments, lessons, and repairs.',
                'website' => 'https://gracewinds.com',
                'address' => '137 SW 3rd St, Corvallis, OR',
                'contact_phone' => '541-754-6295',
            ],
            [
                'name' => 'Guitar Center',
                'description' => 'Large selection of guitars, drums, keyboards, and pro audio equipment.',
                'website' => 'https://guitarcenter.com',
                'address' => '1845 NW 9th St, Corvallis, OR',
                'contact_phone' => '541-752-7800',
            ],
            [
                'name' => 'Sid Stevens Music',
                'description' => 'Family-owned shop specializing in band and orchestra instruments.',
                'website' => 'https://sidstevensmusic.com',
                'address' => '2600 NW 9th St, Corvallis, OR',
                'contact_phone' => '541-753-8576',
            ],
        ]);

        // Recording Studios
        $studios = ResourceList::create([
            'name' => 'Recording Studios',
            'slug' => 'recording-studios',
            'description' => 'Professional recording facilities in the Corvallis area.',
            'published_at' => now(),
            'display_order' => 2,
        ]);

        $this->createResources($studios, [
            [
                'name' => 'Gopher Broke Studios',
                'description' => 'Professional recording, mixing, and mastering services.',
                'website' => 'https://gopherbrokestudios.com',
                'contact_name' => 'Mike Johnson',
                'contact_email' => 'info@gopherbrokestudios.com',
                'address' => 'Corvallis, OR',
            ],
            [
                'name' => 'Falcon Recording Studio',
                'description' => 'Analog and digital recording with vintage gear.',
                'contact_name' => 'Dave Falcon',
                'contact_email' => 'dave@falconrecording.com',
                'contact_phone' => '541-555-0123',
            ],
        ]);

        // Instrument Repair
        $repair = ResourceList::create([
            'name' => 'Instrument Repair',
            'slug' => 'instrument-repair',
            'description' => 'Trusted repair services for guitars, brass, woodwinds, and more.',
            'published_at' => now(),
            'display_order' => 3,
        ]);

        $this->createResources($repair, [
            [
                'name' => 'Gracewinds Repair Shop',
                'description' => 'Expert repairs for guitars, band instruments, and orchestral strings.',
                'website' => 'https://gracewinds.com/repairs',
                'address' => '137 SW 3rd St, Corvallis, OR',
                'contact_phone' => '541-754-6295',
            ],
            [
                'name' => 'Valley Guitar Repair',
                'description' => 'Specializing in acoustic and electric guitar setups and repairs.',
                'contact_name' => 'Tom Wilson',
                'contact_phone' => '541-555-0456',
            ],
            [
                'name' => 'Brass & Reed Works',
                'description' => 'Band instrument repair and restoration.',
                'contact_name' => 'Sarah Chen',
                'contact_email' => 'sarah@brassreedworks.com',
            ],
        ]);

        // Music Teachers
        $teachers = ResourceList::create([
            'name' => 'Music Teachers',
            'slug' => 'music-teachers',
            'description' => 'Private lesson instructors for various instruments and voice.',
            'published_at' => now(),
            'display_order' => 4,
        ]);

        $this->createResources($teachers, [
            [
                'name' => 'Corvallis Guitar Lessons',
                'description' => 'Guitar lessons for all ages and skill levels. Rock, jazz, classical, and more.',
                'website' => 'https://corvallisguitarlessons.com',
                'contact_name' => 'James Miller',
                'contact_email' => 'james@corvallisguitarlessons.com',
            ],
            [
                'name' => 'Willamette Valley Voice Studio',
                'description' => 'Classical and contemporary voice training.',
                'contact_name' => 'Emily Roberts',
                'contact_phone' => '541-555-0789',
            ],
            [
                'name' => 'Drum Dynamics',
                'description' => 'Drum and percussion lessons for beginners to advanced players.',
                'contact_name' => 'Alex Thompson',
                'contact_email' => 'alex@drumdynamics.net',
            ],
        ]);

        // Merch & Design
        $merch = ResourceList::create([
            'name' => 'Merch & Design',
            'slug' => 'merch-design',
            'description' => 'Local artists and shops for band merchandise, posters, and apparel.',
            'published_at' => now(),
            'display_order' => 5,
        ]);

        $this->createResources($merch, [
            [
                'name' => 'Inkwell Press',
                'description' => 'Screen printing and poster design for bands and venues.',
                'website' => 'https://inkwellpress.co',
                'contact_name' => 'Chris Martinez',
                'contact_email' => 'orders@inkwellpress.co',
                'address' => 'Albany, OR',
            ],
            [
                'name' => 'Valley Custom Apparel',
                'description' => 'T-shirts, hoodies, and custom merchandise for bands.',
                'website' => 'https://valleycustomapparel.com',
                'contact_phone' => '541-555-0321',
            ],
        ]);

        // Venues (draft example)
        $venues = ResourceList::create([
            'name' => 'Venues',
            'slug' => 'venues',
            'description' => 'Local venues that host live music.',
            'published_at' => null, // Draft
            'display_order' => 6,
        ]);

        $this->createResources($venues, [
            [
                'name' => 'The Whiteside Theatre',
                'description' => 'Historic theater hosting concerts and events.',
                'website' => 'https://whitesidetheatre.org',
                'address' => '361 SW Madison Ave, Corvallis, OR',
                'published_at' => null, // Also draft
            ],
        ]);

        $this->command->info('Created ' . ResourceList::count() . ' resource lists with ' . LocalResource::count() . ' resources.');
    }

    /**
     * Create resources for a list.
     */
    private function createResources(ResourceList $list, array $resources): void
    {
        foreach ($resources as $index => $data) {
            LocalResource::create([
                'resource_list_id' => $list->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'website' => $data['website'] ?? null,
                'contact_name' => $data['contact_name'] ?? null,
                'contact_email' => $data['contact_email'] ?? null,
                'contact_phone' => $data['contact_phone'] ?? null,
                'address' => $data['address'] ?? null,
                'published_at' => $data['published_at'] ?? now(),
                'sort_order' => $index + 1,
            ]);
        }
    }
}
