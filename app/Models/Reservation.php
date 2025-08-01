<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents a reservation at the practice space.
 * It includes details about the user who made the reservation,
 * a production associated with the reservation (if applicable),
 * and the status of the reservation.
 */

class Reservation extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'production_id',
        'status',
        'reserved_at',
        'reserved_until',
        'cost',
        'hours_used',
        'free_hours_used',
        'is_recurring',
        'recurrence_pattern',
        'notes'
    ];

    protected $casts = [
        'reserved_at' => 'datetime',
        'reserved_until' => 'datetime',
        'cost' => 'decimal:2',
        'hours_used' => 'decimal:2',
        'free_hours_used' => 'decimal:2',
        'is_recurring' => 'boolean',
        'recurrence_pattern' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function production()
    {
        return $this->belongsTo(Production::class);
    }

    /**
     * Get the duration of the reservation in hours.
     */
    public function getDurationAttribute(): float
    {
        if (!$this->reserved_at || !$this->reserved_until) {
            return 0;
        }
        
        return $this->reserved_at->diffInMinutes($this->reserved_until) / 60;
    }

    /**
     * Get a formatted time range for display.
     */
    public function getTimeRangeAttribute(): string
    {
        if (!$this->reserved_at || !$this->reserved_until) {
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
     * Check if reservation is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
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
     * Get formatted cost display.
     */
    public function getCostDisplayAttribute(): string
    {
        if ($this->cost == 0) {
            return 'Free';
        }
        
        return '$' . number_format($this->cost, 2);
    }

}
