<?php

namespace CorvMC\Events\Data;

use Spatie\LaravelData\Data;

class LocationData extends Data
{
    public function __construct(
        public bool $is_external = false,
        public ?string $details = null,
    ) {}

    /**
     * Get the venue display name.
     */
    public function getVenueName(): string
    {
        if ($this->is_external) {
            return 'External Venue';
        }

        return 'Corvallis Music Collective';
    }

    /**
     * Get the full venue details for display.
     */
    public function getVenueDetails(): string
    {
        if ($this->is_external) {
            return $this->details ?? 'External Venue';
        }

        return 'Corvallis Music Collective';
    }

    /**
     * Check if this is an external venue.
     */
    public function isExternal(): bool
    {
        return $this->is_external;
    }

    /**
     * Create a default CMC location.
     */
    public static function cmc(): self
    {
        return new self(is_external: false);
    }

    /**
     * Create an external venue location.
     */
    public static function external(string $details): self
    {
        return new self(is_external: true, details: $details);
    }
}
