<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ReservationService
{
    public const HOURLY_RATE = 15.00;
    public const SUSTAINING_MEMBER_FREE_HOURS = 4;
    public const MIN_RESERVATION_DURATION = 1; // hours
    public const MAX_RESERVATION_DURATION = 8; // hours
    
    /**
     * Calculate the cost for a reservation.
     */
    public function calculateCost(User $user, Carbon $startTime, Carbon $endTime): array
    {
        $hours = $this->calculateHours($startTime, $endTime);
        $remainingFreeHours = $user->getRemainingFreeHours();
        
        $freeHours = $user->isSustainingMember() ? min($hours, $remainingFreeHours) : 0;
        $paidHours = max(0, $hours - $freeHours);
        
        $cost = $paidHours * self::HOURLY_RATE;
        
        return [
            'total_hours' => $hours,
            'free_hours' => $freeHours,
            'paid_hours' => $paidHours,
            'cost' => $cost,
            'hourly_rate' => self::HOURLY_RATE,
            'is_sustaining_member' => $user->isSustainingMember(),
            'remaining_free_hours' => $remainingFreeHours,
        ];
    }
    
    /**
     * Calculate duration in hours between two times.
     */
    public function calculateHours(Carbon $startTime, Carbon $endTime): float
    {
        return $startTime->diffInMinutes($endTime) / 60;
    }
    
    /**
     * Check if a time slot is available.
     */
    public function isTimeSlotAvailable(Carbon $startTime, Carbon $endTime, ?int $excludeReservationId = null): bool
    {
        $query = Reservation::query()
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) use ($startTime, $endTime) {
                $q->whereBetween('reserved_at', [$startTime, $endTime])
                  ->orWhereBetween('reserved_until', [$startTime, $endTime])
                  ->orWhere(function ($sq) use ($startTime, $endTime) {
                      $sq->where('reserved_at', '<=', $startTime)
                         ->where('reserved_until', '>=', $endTime);
                  });
            });
            
        if ($excludeReservationId) {
            $query->where('id', '!=', $excludeReservationId);
        }
        
        return $query->count() === 0;
    }
    
    /**
     * Get conflicting reservations for a time slot.
     */
    public function getConflictingReservations(Carbon $startTime, Carbon $endTime, ?int $excludeReservationId = null): Collection
    {
        $query = Reservation::with('user')
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) use ($startTime, $endTime) {
                $q->whereBetween('reserved_at', [$startTime, $endTime])
                  ->orWhereBetween('reserved_until', [$startTime, $endTime])
                  ->orWhere(function ($sq) use ($startTime, $endTime) {
                      $sq->where('reserved_at', '<=', $startTime)
                         ->where('reserved_until', '>=', $endTime);
                  });
            });
            
        if ($excludeReservationId) {
            $query->where('id', '!=', $excludeReservationId);
        }
        
        return $query->get();
    }
    
    /**
     * Validate reservation parameters.
     */
    public function validateReservation(User $user, Carbon $startTime, Carbon $endTime, ?int $excludeReservationId = null): array
    {
        $errors = [];
        
        // Check if start time is in the future
        if ($startTime->isPast()) {
            $errors[] = 'Reservation start time must be in the future.';
        }
        
        // Check if end time is after start time
        if ($endTime->lte($startTime)) {
            $errors[] = 'End time must be after start time.';
        }
        
        $hours = $this->calculateHours($startTime, $endTime);
        
        // Check minimum duration
        if ($hours < self::MIN_RESERVATION_DURATION) {
            $errors[] = 'Minimum reservation duration is ' . self::MIN_RESERVATION_DURATION . ' hour(s).';
        }
        
        // Check maximum duration
        if ($hours > self::MAX_RESERVATION_DURATION) {
            $errors[] = 'Maximum reservation duration is ' . self::MAX_RESERVATION_DURATION . ' hours.';
        }
        
        // Check for conflicts
        if (!$this->isTimeSlotAvailable($startTime, $endTime, $excludeReservationId)) {
            $conflicts = $this->getConflictingReservations($startTime, $endTime, $excludeReservationId);
            $errors[] = 'Time slot conflicts with existing reservation(s): ' . 
                       $conflicts->map(fn($r) => $r->user->name . ' (' . $r->reserved_at->format('M j, g:i A') . ' - ' . $r->reserved_until->format('g:i A') . ')')->join(', ');
        }
        
        // Business hours check (9 AM to 10 PM)
        if ($startTime->hour < 9 || $endTime->hour > 22 || ($endTime->hour == 22 && $endTime->minute > 0)) {
            $errors[] = 'Reservations are only allowed between 9 AM and 10 PM.';
        }
        
        return $errors;
    }
    
    /**
     * Create a new reservation.
     */
    public function createReservation(User $user, Carbon $startTime, Carbon $endTime, array $options = []): Reservation
    {
        $errors = $this->validateReservation($user, $startTime, $endTime);
        
        if (!empty($errors)) {
            throw new \InvalidArgumentException('Validation failed: ' . implode(' ', $errors));
        }
        
        $costCalculation = $this->calculateCost($user, $startTime, $endTime);
        
        return DB::transaction(function () use ($user, $startTime, $endTime, $costCalculation, $options) {
            return Reservation::create([
                'user_id' => $user->id,
                'reserved_at' => $startTime,
                'reserved_until' => $endTime,
                'cost' => $costCalculation['cost'],
                'hours_used' => $costCalculation['total_hours'],
                'free_hours_used' => $costCalculation['free_hours'],
                'status' => $options['status'] ?? 'confirmed',
                'notes' => $options['notes'] ?? null,
                'is_recurring' => $options['is_recurring'] ?? false,
                'recurrence_pattern' => $options['recurrence_pattern'] ?? null,
                'production_id' => $options['production_id'] ?? null,
            ]);
        });
    }
    
    /**
     * Update an existing reservation.
     */
    public function updateReservation(Reservation $reservation, Carbon $startTime, Carbon $endTime, array $options = []): Reservation
    {
        $errors = $this->validateReservation($reservation->user, $startTime, $endTime, $reservation->id);
        
        if (!empty($errors)) {
            throw new \InvalidArgumentException('Validation failed: ' . implode(' ', $errors));
        }
        
        $costCalculation = $this->calculateCost($reservation->user, $startTime, $endTime);
        
        return DB::transaction(function () use ($reservation, $startTime, $endTime, $costCalculation, $options) {
            $reservation->update([
                'reserved_at' => $startTime,
                'reserved_until' => $endTime,
                'cost' => $costCalculation['cost'],
                'hours_used' => $costCalculation['total_hours'],
                'free_hours_used' => $costCalculation['free_hours'],
                'notes' => $options['notes'] ?? $reservation->notes,
                'status' => $options['status'] ?? $reservation->status,
            ]);
            
            return $reservation;
        });
    }
    
    /**
     * Cancel a reservation.
     */
    public function cancelReservation(Reservation $reservation, string $reason = null): Reservation
    {
        $reservation->update([
            'status' => 'cancelled',
            'notes' => $reservation->notes . ($reason ? "\nCancellation reason: " . $reason : ''),
        ]);
        
        return $reservation;
    }
    
    /**
     * Get available time slots for a given date.
     */
    public function getAvailableTimeSlots(Carbon $date, int $durationHours = 1): array
    {
        $slots = [];
        $startHour = 9; // 9 AM
        $endHour = 22; // 10 PM
        
        for ($hour = $startHour; $hour <= $endHour - $durationHours; $hour++) {
            $slotStart = $date->copy()->setTime($hour, 0);
            $slotEnd = $slotStart->copy()->addHours($durationHours);
            
            if ($this->isTimeSlotAvailable($slotStart, $slotEnd)) {
                $slots[] = [
                    'start' => $slotStart,
                    'end' => $slotEnd,
                    'duration' => $durationHours,
                ];
            }
        }
        
        return $slots;
    }
    
    /**
     * Create recurring reservations for sustaining members.
     */
    public function createRecurringReservation(User $user, Carbon $startTime, Carbon $endTime, array $recurrencePattern): array
    {
        if (!$user->isSustainingMember()) {
            throw new \InvalidArgumentException('Only sustaining members can create recurring reservations.');
        }
        
        $reservations = [];
        $weeks = $recurrencePattern['weeks'] ?? 4; // Default to 4 weeks
        $interval = $recurrencePattern['interval'] ?? 1; // Every N weeks
        
        for ($i = 0; $i < $weeks; $i++) {
            $weekOffset = $i * $interval;
            $recurringStart = $startTime->copy()->addWeeks($weekOffset);
            $recurringEnd = $endTime->copy()->addWeeks($weekOffset);
            
            try {
                $reservation = $this->createReservation($user, $recurringStart, $recurringEnd, [
                    'is_recurring' => true,
                    'recurrence_pattern' => $recurrencePattern,
                    'status' => 'pending', // Recurring reservations need confirmation
                ]);
                
                $reservations[] = $reservation;
            } catch (\InvalidArgumentException $e) {
                // Skip this slot if there's a conflict, but continue with others
                continue;
            }
        }
        
        return $reservations;
    }
    
    /**
     * Get user's reservation statistics.
     */
    public function getUserStats(User $user): array
    {
        $thisMonth = now()->startOfMonth();
        $thisYear = now()->startOfYear();
        
        return [
            'total_reservations' => $user->reservations()->count(),
            'this_month_reservations' => $user->reservations()->where('reserved_at', '>=', $thisMonth)->count(),
            'this_year_hours' => $user->reservations()->where('reserved_at', '>=', $thisYear)->sum('hours_used'),
            'this_month_hours' => $user->reservations()->where('reserved_at', '>=', $thisMonth)->sum('hours_used'),
            'free_hours_used' => $user->getUsedFreeHoursThisMonth(),
            'remaining_free_hours' => $user->getRemainingFreeHours(),
            'total_spent' => $user->reservations()->sum('cost'),
            'is_sustaining_member' => $user->isSustainingMember(),
        ];
    }
}