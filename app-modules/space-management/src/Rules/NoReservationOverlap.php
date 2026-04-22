<?php

namespace CorvMC\SpaceManagement\Rules;

use Carbon\Carbon;
use Closure;
use CorvMC\SpaceManagement\Facades\ReservationService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Collection;

class NoReservationOverlap implements ValidationRule
{
    protected ?int $excludeId;
    protected bool $includeBuffer;
    protected ?Collection $conflicts = null;

    public function __construct(
        ?int $excludeId = null,
        bool $includeBuffer = true
    ) {
        $this->excludeId = $excludeId;
        $this->includeBuffer = $includeBuffer;
    }

    /**
     * Run the validation rule.
     *
     * @param  string  $attribute
     * @param  mixed  $value  Array with 'start_time' and 'end_time'
     * @param  \Closure  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_array($value) || !isset($value['start_time']) || !isset($value['end_time'])) {
            $fail('Invalid time slot data for reservation overlap check.');
            return;
        }

        $startTime = $value['start_time'] instanceof Carbon
            ? $value['start_time']
            : Carbon::parse($value['start_time']);

        $endTime = $value['end_time'] instanceof Carbon
            ? $value['end_time']
            : Carbon::parse($value['end_time']);

        // Get conflicting reservations (returns a Collection)
        $conflicts = ReservationService::getConflicts(
            $startTime,
            $endTime,
            excludeId: $this->excludeId,
            includeBuffer: $this->includeBuffer,
            includeClosures: false
        );

        $this->conflicts = $conflicts;

        if ($this->conflicts->isNotEmpty()) {
            $count = $this->conflicts->count();
            $fail("Conflicts with {$count} existing reservation(s).");
        }
    }

    /**
     * Get the conflicting reservations found during validation.
     */
    public function getConflicts(): ?Collection
    {
        return $this->conflicts;
    }
}
