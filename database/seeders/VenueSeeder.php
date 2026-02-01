<?php

namespace Database\Seeders;

use CorvMC\Events\Models\Venue;
use Illuminate\Database\Seeder;

class VenueSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Seed Corvallis Music Collective as the primary venue
        Venue::create([
            'name' => 'Corvallis Music Collective',
            'address' => '2525 NW Monroe Ave',  // TODO: Verify actual address
            'city' => 'Corvallis',
            'state' => 'OR',
            'zip' => '97330',
            'is_cmc' => true,
            'distance_from_corvallis' => 0,  // It IS Corvallis
            'notes' => 'Main venue and practice space',
        ]);

        // Seed common external venues from EventFactory test data
        $externalVenues = [
            [
                'name' => 'The Underground',
                'address' => '123 Main St',
                'city' => 'Corvallis',
                'state' => 'OR',
                'zip' => '97330',
            ],
            [
                'name' => 'City Music Hall',
                'address' => '456 Oak Ave',
                'city' => 'Corvallis',
                'state' => 'OR',
                'zip' => '97330',
            ],
            [
                'name' => 'Riverside Amphitheater',
                'address' => '789 River Rd',
                'city' => 'Corvallis',
                'state' => 'OR',
                'zip' => '97333',
            ],
            [
                'name' => 'The Corner Stage',
                'address' => '321 2nd St',
                'city' => 'Corvallis',
                'state' => 'OR',
                'zip' => '97330',
            ],
            [
                'name' => 'Main Street Venue',
                'address' => '654 Main St',
                'city' => 'Corvallis',
                'state' => 'OR',
                'zip' => '97330',
            ],
            [
                'name' => 'Park Pavilion',
                'address' => 'Avery Park',
                'city' => 'Corvallis',
                'state' => 'OR',
                'zip' => '97330',
                'notes' => 'Outdoor venue in Avery Park',
            ],
            [
                'name' => 'Community Center',
                'address' => '2121 NW Kings Blvd',
                'city' => 'Corvallis',
                'state' => 'OR',
                'zip' => '97330',
            ],
            [
                'name' => 'The Music Box',
                'address' => '987 Monroe Ave',
                'city' => 'Corvallis',
                'state' => 'OR',
                'zip' => '97330',
            ],
            [
                'name' => 'Majestic Theatre',
                'address' => '115 SW 2nd St',
                'city' => 'Corvallis',
                'state' => 'OR',
                'zip' => '97333',
                'is_cmc' => false,
                'notes' => 'Historic downtown theatre',
            ],
        ];

        foreach ($externalVenues as $venueData) {
            Venue::create($venueData);
        }
    }
}
