<?php

use CorvMC\Events\Models\Event;
use App\Models\Venue;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Migrate existing event location JSON data to venue_id relationships.
     * Parse location DTO and either link to CMC or create/match external venues.
     */
    public function up(): void
    {
        // Get or create CMC venue
        $cmcVenue = Venue::cmc()->first();

        if (! $cmcVenue) {
            // Create CMC venue if it doesn't exist (e.g., in test environments)
            $cmcVenue = Venue::create([
                'name' => 'Corvallis Music Collective',
                'address' => '2121 NW Kings Blvd',
                'city' => 'Corvallis',
                'state' => 'OR',
                'zip' => '97330',
                'is_cmc' => true,
            ]);
        }

        // Process all events using raw database queries for stability
        DB::table('events')->orderBy('id')->chunk(100, function ($events) use ($cmcVenue) {
            foreach ($events as $event) {
                // Skip if already has venue_id (shouldn't happen, but be safe)
                if ($event->venue_id) {
                    continue;
                }

                // Parse location JSON directly from database
                $locationJson = $event->location;

                if (! $locationJson) {
                    // No location data, default to CMC
                    DB::table('events')
                        ->where('id', $event->id)
                        ->update(['venue_id' => $cmcVenue->id]);

                    continue;
                }

                // Decode JSON to array
                $location = is_string($locationJson) ? json_decode($locationJson, true) : (array) $locationJson;

                // Check if external venue
                $isExternal = $location['is_external'] ?? false;

                if (! $isExternal) {
                    // CMC event
                    DB::table('events')
                        ->where('id', $event->id)
                        ->update(['venue_id' => $cmcVenue->id]);
                } else {
                    // External event - parse details
                    $details = $location['details'] ?? '';

                    if (empty($details)) {
                        // External but no details, still default to CMC
                        DB::table('events')
                            ->where('id', $event->id)
                            ->update(['venue_id' => $cmcVenue->id]);

                        continue;
                    }

                    // Try to parse venue name and address from details
                    // Format is usually: "Venue Name - Address" or just "Full Address"
                    $venueName = null;
                    $venueAddress = $details;

                    if (str_contains($details, ' - ')) {
                        [$venueName, $venueAddress] = explode(' - ', $details, 2);
                        $venueName = trim($venueName);
                        $venueAddress = trim($venueAddress);
                    } else {
                        // Try to extract venue name from first part before comma
                        $parts = explode(',', $details);
                        if (count($parts) > 1) {
                            $venueName = trim($parts[0]);
                        } else {
                            $venueName = 'External Venue';
                        }
                    }

                    // Try to find existing venue by name (case-insensitive)
                    $venue = Venue::whereRaw('LOWER(name) = ?', [strtolower($venueName)])->first();

                    if (! $venue) {
                        // Create new venue
                        $venue = Venue::create([
                            'name' => $venueName,
                            'address' => $venueAddress,
                            'city' => $this->extractCity($venueAddress),
                            'state' => $this->extractState($venueAddress),
                            'zip' => $this->extractZip($venueAddress),
                            'is_cmc' => false,
                        ]);
                    }

                    DB::table('events')
                        ->where('id', $event->id)
                        ->update(['venue_id' => $venue->id]);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Set all venue_id back to null
        DB::table('events')->update(['venue_id' => null]);
    }

    /**
     * Extract city from address string.
     */
    private function extractCity(string $address): string
    {
        // Look for "Corvallis" or default to Corvallis
        if (stripos($address, 'corvallis') !== false) {
            return 'Corvallis';
        }

        // Try to extract city from pattern: "street, city, state zip"
        $parts = explode(',', $address);
        if (count($parts) >= 2) {
            return trim($parts[count($parts) - 2]);
        }

        return 'Corvallis'; // Default
    }

    /**
     * Extract state from address string.
     */
    private function extractState(string $address): string
    {
        // Look for state abbreviations
        if (preg_match('/\b([A-Z]{2})\b/', $address, $matches)) {
            return $matches[1];
        }

        return 'OR'; // Default
    }

    /**
     * Extract ZIP code from address string.
     */
    private function extractZip(string $address): ?string
    {
        // Look for 5-digit ZIP code
        if (preg_match('/\b(\d{5})(?:-\d{4})?\b/', $address, $matches)) {
            return $matches[1];
        }

        return null;
    }
};
