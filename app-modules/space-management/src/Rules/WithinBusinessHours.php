<?php

namespace CorvMC\SpaceManagement\Rules;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class WithinBusinessHours implements ValidationRule
{
    protected int $openHour;
    protected int $closeHour;
    
    public function __construct(int $openHour = 9, int $closeHour = 22)
    {
        $this->openHour = $openHour;
        $this->closeHour = $closeHour;
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
            $fail('Invalid time slot data for business hours check.');
            return;
        }
        
        $startTime = $value['start_time'] instanceof Carbon 
            ? $value['start_time'] 
            : Carbon::parse($value['start_time']);
            
        $endTime = $value['end_time'] instanceof Carbon 
            ? $value['end_time'] 
            : Carbon::parse($value['end_time']);
        
        // Check if start time is within business hours
        if ($startTime->hour < $this->openHour) {
            $fail("Reservations cannot start before {$this->openHour}:00 AM.");
            return;
        }
        
        // Check if end time is within business hours
        if ($endTime->hour > $this->closeHour || 
            ($endTime->hour == $this->closeHour && $endTime->minute > 0)) {
            $closeFormatted = $this->closeHour > 12 
                ? ($this->closeHour - 12) . ':00 PM' 
                : $this->closeHour . ':00 AM';
            $fail("Reservations must end by {$closeFormatted}.");
        }
    }
}