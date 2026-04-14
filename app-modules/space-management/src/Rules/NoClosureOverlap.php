<?php

namespace CorvMC\SpaceManagement\Rules;

use Carbon\Carbon;
use Closure;
use CorvMC\SpaceManagement\Services\ReservationService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Collection;

class NoClosureOverlap implements ValidationRule
{
    protected ?Collection $closures = null;
    
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
            $fail('Invalid time slot data for closure check.');
            return;
        }
        
        $startTime = $value['start_time'] instanceof Carbon 
            ? $value['start_time'] 
            : Carbon::parse($value['start_time']);
            
        $endTime = $value['end_time'] instanceof Carbon 
            ? $value['end_time'] 
            : Carbon::parse($value['end_time']);
        
        // Get conflicting closures
        $reservationService = app(ReservationService::class);
        $conflicts = $reservationService->getConflicts($startTime, $endTime, [
            'includeClosures' => true,
            'includeBuffer' => false,
            'excludeId' => null,
        ]);
        
        $this->closures = $conflicts['closures'] ?? collect();
        
        if ($this->closures->isNotEmpty()) {
            $fail('Space is closed during this time.');
        }
    }
    
    /**
     * Get the closures found during validation.
     */
    public function getClosures(): ?Collection
    {
        return $this->closures;
    }
}