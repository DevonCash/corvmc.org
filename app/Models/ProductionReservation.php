<?php

namespace App\Models;

use App\Concerns\HasTimePeriod;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Represents a space reservation for a production/event.
 * Owned by a Production, includes setup and breakdown time.
 */
class ProductionReservation extends Reservation
{
    use HasFactory, HasTimePeriod;

    // Table and primary key are inherited from parent

    /**
     * Get the name of the start time field for this model.
     */
    protected function getStartTimeField(): string
    {
        return 'reserved_at';
    }

    /**
     * Get the name of the end time field for this model.
     */
    protected function getEndTimeField(): string
    {
        return 'reserved_until';
    }

    // Guarded is inherited from parent - using $guarded = ['id'] from Reservation
    // Casts are inherited from parent Reservation class

    /**
     * The production that owns this space reservation.
     */
    public function production()
    {
        return $this->morphTo(__FUNCTION__, 'reservable_type', 'reservable_id');
    }

    // STI Abstract Method Implementations

    public function getReservationTypeLabel(): string
    {
        return 'Production';
    }

    public function getReservationIcon(): string
    {
        return 'tabler-microphone-2';
    }

    public function getDisplayTitle(): string
    {
        return $this->reservable?->title ?? 'Unknown Production';
    }

    public function getResponsibleUser(): User
    {
        return $this->reservable?->manager;
    }

    // Production-Specific Methods

    /**
     * Get the duration of the space reservation in hours.
     */
    public function getDurationAttribute(): float
    {
        if (! $this->reserved_at || ! $this->reserved_until) {
            return 0;
        }

        return $this->reserved_at->diffInMinutes($this->reserved_until) / 60;
    }

    /**
     * Get setup time in hours (before event start).
     */
    public function getSetupTimeAttribute(): float
    {
        if (! $this->reserved_at || ! $this->production?->start_time) {
            return 0;
        }

        return $this->reserved_at->diffInMinutes($this->production->start_time) / 60;
    }

    /**
     * Get breakdown time in hours (after event end).
     */
    public function getBreakdownTimeAttribute(): float
    {
        if (! $this->reserved_until || ! $this->production?->end_time) {
            return 0;
        }

        return $this->production->end_time->diffInMinutes($this->reserved_until) / 60;
    }

    /**
     * Get a formatted time range for display.
     */
    public function getTimeRangeAttribute(): string
    {
        if (! $this->reserved_at || ! $this->reserved_until) {
            return 'TBD';
        }

        if ($this->reserved_at->isSameDay($this->reserved_until)) {
            return $this->reserved_at->format('M j, Y g:i A') . ' - ' . $this->reserved_until->format('g:i A');
        }

        return $this->reserved_at->format('M j, Y g:i A') . ' - ' . $this->reserved_until->format('M j, Y g:i A');
    }

    /**
     * Check if reservation is confirmed.
     */
    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    /**
     * Check if reservation is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if reservation is upcoming.
     */
    public function isUpcoming(): bool
    {
        return $this->reserved_at && $this->reserved_at->isFuture();
    }

    /**
     * Check if reservation is in progress.
     */
    public function isInProgress(): bool
    {
        return $this->reserved_at && $this->reserved_until &&
            $this->reserved_at->isPast() && $this->reserved_until->isFuture();
    }

    /**
     * Production reservations don't have payment tracking.
     * Payment is handled separately via tickets.
     */
    public function getCostDisplayAttribute(): string
    {
        return 'N/A';
    }

    /**
     * Get formatted status display.
     */
    public function getStatusDisplayAttribute(): string
    {
        return ucfirst($this->status ?? '');
    }
}
