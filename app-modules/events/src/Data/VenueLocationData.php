<?php

namespace CorvMC\Events\Data;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Spatie\LaravelData\Data;

class VenueLocationData extends Data
{
    public function __construct(
        public string $venue_name,
        public string $venue_address,
        public ?float $distance_from_corvallis = null,
        public ?string $formatted_address = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
    ) {}

    /**
     * Corvallis, OR coordinates for distance calculation
     */
    private const CORVALLIS_LAT = 44.5646;

    private const CORVALLIS_LNG = -123.2620;

    private const CORVALLIS_ADDRESS = 'Corvallis, OR, USA';

    /**
     * Create venue location from basic venue info.
     */
    public static function create(string $venueName, string $venueAddress): self
    {
        return new self(
            venue_name: $venueName,
            venue_address: $venueAddress
        );
    }

    /**
     * Calculate and set distance from Corvallis using Google Maps API.
     *
     * @param  string|null  $apiKey  Google Maps API key
     */
    public function calculateDistance(?string $apiKey = null): self
    {
        $apiKey = $apiKey ?? config('services.google_maps.api_key');

        if (! $apiKey) {
            Log::warning('Google Maps API key not configured for distance calculation');

            return $this;
        }

        // Cache key for this address
        $cacheKey = 'venue_distance_'.md5($this->venue_address);

        // Try to get cached distance
        $cachedData = Cache::get($cacheKey);
        if ($cachedData) {
            return new self(
                venue_name: $this->venue_name,
                venue_address: $this->venue_address,
                distance_from_corvallis: $cachedData['distance'],
                formatted_address: $cachedData['formatted_address'],
                latitude: $cachedData['latitude'],
                longitude: $cachedData['longitude'],
            );
        }

        try {
            // Get driving time from Google Maps Directions API
            $response = Http::get('https://maps.googleapis.com/maps/api/directions/json', [
                'origin' => self::CORVALLIS_ADDRESS,
                'destination' => $this->venue_address,
                'mode' => 'driving',
                'key' => $apiKey,
            ]);

            $data = $response->json();

            if ($data['status'] === 'OK' && ! empty($data['routes'])) {
                $route = $data['routes'][0];
                $leg = $route['legs'][0];

                // Get driving duration in minutes
                $durationInMinutes = $leg['duration']['value'] / 60;

                // Get formatted address and coordinates
                $endLocation = $leg['end_location'];
                $formattedAddress = $leg['end_address'];

                // Cache the result for 24 hours
                $cacheData = [
                    'distance' => $durationInMinutes,
                    'formatted_address' => $formattedAddress,
                    'latitude' => $endLocation['lat'],
                    'longitude' => $endLocation['lng'],
                ];
                Cache::put($cacheKey, $cacheData, now()->addHours(24));

                return new self(
                    venue_name: $this->venue_name,
                    venue_address: $this->venue_address,
                    distance_from_corvallis: $durationInMinutes,
                    formatted_address: $formattedAddress,
                    latitude: $endLocation['lat'],
                    longitude: $endLocation['lng'],
                );
            } else {
                Log::warning('Google Maps API returned error for address: '.$this->venue_address, [
                    'status' => $data['status'] ?? 'unknown',
                    'error_message' => $data['error_message'] ?? 'No error message',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to calculate distance for venue: '.$this->venue_address, [
                'error' => $e->getMessage(),
            ]);
        }

        return $this;
    }

    /**
     * Check if venue is within acceptable driving distance.
     */
    public function isWithinDistance(float $maxMinutes = 60): bool
    {
        return $this->distance_from_corvallis === null || $this->distance_from_corvallis <= $maxMinutes;
    }

    /**
     * Get formatted driving time display.
     */
    public function getDrivingTimeDisplay(): ?string
    {
        if ($this->distance_from_corvallis === null) {
            return null;
        }

        $hours = floor($this->distance_from_corvallis / 60);
        $minutes = $this->distance_from_corvallis % 60;

        if ($hours > 0) {
            return sprintf('%d hr %d min', $hours, $minutes);
        }

        return sprintf('%d min', $minutes);
    }

    /**
     * Get Google Maps directions URL.
     */
    public function getDirectionsUrl(): string
    {
        $encodedDestination = urlencode($this->venue_address);
        $encodedOrigin = urlencode(self::CORVALLIS_ADDRESS);

        return "https://www.google.com/maps/dir/{$encodedOrigin}/{$encodedDestination}";
    }

    /**
     * Get venue display name with address.
     */
    public function getFullVenueDisplay(): string
    {
        return $this->venue_name.' - '.($this->formatted_address ?? $this->venue_address);
    }

    /**
     * Validate venue address format.
     */
    public function validateAddress(): array
    {
        $errors = [];

        if (empty($this->venue_name)) {
            $errors[] = 'Venue name is required';
        }

        if (empty($this->venue_address)) {
            $errors[] = 'Venue address is required';
        }

        // Basic address validation
        if (! empty($this->venue_address)) {
            // Should contain some common address components
            $hasNumber = preg_match('/\d/', $this->venue_address);
            $hasComma = strpos($this->venue_address, ',') !== false;

            if (! $hasNumber && ! $hasComma) {
                $errors[] = 'Please provide a complete address with street number and city';
            }
        }

        return $errors;
    }

    /**
     * Create venue location for CMC.
     */
    public static function cmc(): self
    {
        return new self(
            venue_name: 'Corvallis Music Collective',
            venue_address: 'Corvallis, OR, USA',
            distance_from_corvallis: 0,
            formatted_address: 'Corvallis, OR, USA',
            latitude: self::CORVALLIS_LAT,
            longitude: self::CORVALLIS_LNG,
        );
    }

    /**
     * Get distance warning message if venue is too far.
     */
    public function getDistanceWarning(float $maxMinutes = 60): ?string
    {
        if ($this->distance_from_corvallis === null) {
            return 'Distance from Corvallis could not be calculated. Please verify the address.';
        }

        if ($this->distance_from_corvallis > $maxMinutes) {
            $drivingTime = $this->getDrivingTimeDisplay();

            return "This venue is {$drivingTime} from Corvallis, which exceeds the recommended {$maxMinutes}-minute limit for community events.";
        }

        return null;
    }
}
