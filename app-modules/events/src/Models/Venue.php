<?php

namespace CorvMC\Events\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * @property int $id
 * @property string $name
 * @property string $address
 * @property string $city
 * @property string $state
 * @property string|null $zip
 * @property float|null $latitude
 * @property float|null $longitude
 * @property int|null $distance_from_corvallis
 * @property \Illuminate\Support\Carbon|null $distance_cached_at
 * @property bool $is_cmc
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Venue extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Corvallis, OR coordinates for distance calculation
     */
    private const CORVALLIS_LAT = 44.5646;

    private const CORVALLIS_LNG = -123.2620;

    private const CORVALLIS_ADDRESS = 'Corvallis, OR, USA';

    protected $fillable = [
        'name',
        'address',
        'city',
        'state',
        'zip',
        'latitude',
        'longitude',
        'distance_from_corvallis',
        'distance_cached_at',
        'is_cmc',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'distance_from_corvallis' => 'integer',
            'distance_cached_at' => 'datetime',
            'is_cmc' => 'boolean',
        ];
    }

    /**
     * Get the full formatted address.
     */
    public function getFormattedAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->state.($this->zip ? ' '.$this->zip : ''),
        ]);

        return implode(', ', $parts);
    }

    /**
     * Get venue display name with address.
     */
    public function getFullVenueDisplayAttribute(): string
    {
        return $this->name.' - '.$this->formatted_address;
    }

    /**
     * Calculate and update distance from Corvallis using Google Maps API.
     */
    public function calculateDistance(?string $apiKey = null): bool
    {
        $apiKey = $apiKey ?? config('services.google_maps.api_key');

        if (! $apiKey) {
            Log::warning('Google Maps API key not configured for distance calculation');

            return false;
        }

        // Skip calculation for CMC venue
        if ($this->is_cmc) {
            return true;
        }

        try {
            // Get driving time from Google Maps Directions API
            $response = Http::get('https://maps.googleapis.com/maps/api/directions/json', [
                'origin' => self::CORVALLIS_ADDRESS,
                'destination' => $this->formatted_address,
                'mode' => 'driving',
                'key' => $apiKey,
            ]);

            $data = $response->json();

            if ($data['status'] === 'OK' && ! empty($data['routes'])) {
                $route = $data['routes'][0];
                $leg = $route['legs'][0];

                // Get driving duration in minutes
                $durationInMinutes = (int) ($leg['duration']['value'] / 60);

                // Get coordinates
                $endLocation = $leg['end_location'];

                // Update the model
                $this->update([
                    'distance_from_corvallis' => $durationInMinutes,
                    'latitude' => $endLocation['lat'],
                    'longitude' => $endLocation['lng'],
                    'distance_cached_at' => now(),
                ]);

                return true;
            } else {
                Log::warning('Google Maps API returned error for address: '.$this->formatted_address, [
                    'status' => $data['status'] ?? 'unknown',
                    'error_message' => $data['error_message'] ?? 'No error message',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to calculate distance for venue: '.$this->formatted_address, [
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * Check if distance cache is stale (older than 24 hours).
     */
    public function isDistanceCacheStale(): bool
    {
        if (! $this->distance_cached_at) {
            return true;
        }

        return $this->distance_cached_at->lt(now()->subHours(24));
    }

    /**
     * Check if venue is within acceptable driving distance.
     */
    public function isWithinDistance(int $maxMinutes = 60): bool
    {
        return $this->distance_from_corvallis === null || $this->distance_from_corvallis <= $maxMinutes;
    }

    /**
     * Get formatted driving time display.
     */
    public function getDrivingTimeDisplayAttribute(): ?string
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
    public function getDirectionsUrlAttribute(): string
    {
        $encodedDestination = urlencode($this->formatted_address);
        $encodedOrigin = urlencode(self::CORVALLIS_ADDRESS);

        return "https://www.google.com/maps/dir/{$encodedOrigin}/{$encodedDestination}";
    }

    /**
     * Get all events at this venue.
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * Scope to get only CMC venue.
     */
    public function scopeCmc(Builder $query): Builder
    {
        return $query->where('is_cmc', true);
    }

    /**
     * Scope to get external venues (not CMC).
     */
    public function scopeExternal(Builder $query): Builder
    {
        return $query->where('is_cmc', false);
    }

    /**
     * Scope to get venues within a certain distance.
     */
    public function scopeWithinDistance(Builder $query, int $maxMinutes): Builder
    {
        return $query->where('distance_from_corvallis', '<=', $maxMinutes)
            ->orWhereNull('distance_from_corvallis');
    }
}
