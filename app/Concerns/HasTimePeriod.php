<?php

namespace App\Concerns;

use Spatie\Period\Period;
use Spatie\Period\Precision;

trait HasTimePeriod
{
    /**
     * Create a Period object from the model's time fields.
     */
    public function createPeriod(): ?Period
    {
        $startField = $this->getStartTimeField();
        $endField = $this->getEndTimeField();
        
        if (!$this->{$startField} || !$this->{$endField}) {
            return null;
        }
        
        return Period::make(
            $this->{$startField},
            $this->{$endField},
            Precision::MINUTE()
        );
    }
    
    /**
     * Check if this model's time period overlaps with another period.
     */
    public function periodOverlapsWith(Period $period): bool
    {
        $thisPeriod = $this->createPeriod();
        
        if (!$thisPeriod) {
            return false;
        }
        
        return $thisPeriod?->overlapsWith($period);
    }
    
    /**
     * Check if this model's time period overlaps with another model's period.
     */
    public function overlapsWith($other): bool
    {
        if (!method_exists($other, 'createPeriod')) {
            return false;
        }
        
        $otherPeriod = $other->createPeriod();
        
        if (!$otherPeriod) {
            return false;
        }
        
        return $this->overlapsWithPeriod($otherPeriod);
    }
    
    /**
     * Validate that the time period is valid (start before end).
     */
    public function isValidTimePeriod(): bool
    {
        $startField = $this->getStartTimeField();
        $endField = $this->getEndTimeField();
        
        if (!$this->{$startField} || !$this->{$endField}) {
            return false;
        }
        
        return $this->{$startField} < $this->{$endField};
    }
    
    /**
     * Get the name of the start time field for this model.
     * Override in models if different from default.
     */
    protected function getStartTimeField(): string
    {
        return 'start_time';
    }
    
    /**
     * Get the name of the end time field for this model.
     * Override in models if different from default.
     */
    protected function getEndTimeField(): string
    {
        return 'end_time';
    }
}