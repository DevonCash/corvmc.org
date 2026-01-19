<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;
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

        if (! $this->{$startField} || ! $this->{$endField}) {
            return null;
        }

        return Period::make(
            $this->{$startField},
            $this->{$endField},
            Precision::MINUTE()
        );
    }

    public function getPeriodAttribute(): ?Period
    {
        return $this->createPeriod();
    }

    public function getDurationAttribute(): float
    {
        $period = $this->createPeriod();

        if (! $period) {
            return 0;
        }

        return round($period->length() / 30) / 2.0;
    }

    /**
     * Check if this model's time period overlaps with another period.
     */
    public function periodOverlapsWith(Period $period): bool
    {
        $thisPeriod = $this->createPeriod();

        if (! $thisPeriod) {
            return false;
        }

        return $thisPeriod?->overlapsWith($period);
    }

    /**
     * Check if this model's time period overlaps with another model's period or Period object.
     */
    public function overlapsWith($other): bool
    {
        // If it's a Period object, use periodOverlapsWith directly
        if ($other instanceof Period) {
            return $this->periodOverlapsWith($other);
        }

        // Otherwise, expect an object with createPeriod() method
        if (! method_exists($other, 'createPeriod')) {
            return false;
        }

        $otherPeriod = $other->createPeriod();

        if (! $otherPeriod) {
            return false;
        }

        return $this->periodOverlapsWith($otherPeriod);
    }

    /**
     * Validate that the time period is valid (start before end).
     */
    public function isValidTimePeriod(): bool
    {
        $startField = $this->getStartTimeField();
        $endField = $this->getEndTimeField();

        if (! $this->{$startField} || ! $this->{$endField}) {
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

    /**
     * Scope to filter by common date ranges.
     */
    public function scopeDateRange(Builder $query, string $range): Builder
    {
        $startField = $this->getStartTimeField();

        switch ($range) {
            case 'this_week':
                return $query->whereBetween($startField, [now()->startOfWeek(), now()->endOfWeek()]);
            case 'this_month':
                return $query->whereBetween($startField, [now()->startOfMonth(), now()->endOfMonth()]);
            case 'next_month':
                return $query->whereBetween($startField, [now()->addMonth()->startOfMonth(), now()->addMonth()->endOfMonth()]);
            default:
                return $query;
        }
    }
}
