<?php

namespace CorvMC\Support\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Spatie\Period\Period;
use Spatie\Period\Precision;

trait HasTimePeriod
{
    public function getStartTime(): ?\DateTimeInterface
    {
        $startField = $this->getStartTimeField();
        return $this->{$startField} ?? null;
    }

    public function getEndTime(): ?\DateTimeInterface
    {
        $endField = $this->getEndTimeField();
        return $this->{$endField} ?? null;
    }

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

        if ($this->{$startField} >= $this->{$endField}) {
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
     * Models should define protected static string $startTimeField to customize.
     */
    protected function getStartTimeField(): string
    {
        return static::$startTimeField ?? 'start_time';
    }

    /**
     * Get the name of the end time field for this model.
     * Models should define protected static string $endTimeField to customize.
     */
    protected function getEndTimeField(): string
    {
        return static::$endTimeField ?? 'end_time';
    }

    /**
     * Scope to get items that overlap with a given time period.
     *
     * @param Builder $query
     * @param \DateTimeInterface|string $start
     * @param \DateTimeInterface|string $end
     * @param bool $strict If true, only return items fully contained within the period
     * @return Builder
     */
    public function scopeOverlapping(Builder $query, $start, $end, bool $strict = false): Builder
    {
        $startField = $this->getStartTimeField();
        $endField = $this->getEndTimeField();

        if ($strict) {
            // Items fully contained within the period
            return $query->where($startField, '>=', $start)
                ->where($endField, '<=', $end);
        }

        // Items with any overlap
        return $query->where($endField, '>', $start)
            ->where($startField, '<', $end);
    }

    /**
     * Scope to get items that have started.
     */
    public function scopeStarted(Builder $query): Builder
    {
        $startField = $this->getStartTimeField();
        return $query->where($startField, '<=', now());
    }

    /**
     * Scope to get items that have not started yet.
     */
    public function scopeNotStarted(Builder $query): Builder
    {
        $startField = $this->getStartTimeField();
        return $query->where($startField, '>', now());
    }

    /**
     * Scope to get items that have ended.
     */
    public function scopeEnded(Builder $query): Builder
    {
        $endField = $this->getEndTimeField();
        return $query->where($endField, '<', now());
    }

    /**
     * Scope to get items that have not ended yet.
     */
    public function scopeNotEnded(Builder $query): Builder
    {
        $endField = $this->getEndTimeField();
        return $query->where($endField, '>=', now());
    }

    /**
     * Scope to filter by duration.
     *
     * @param Builder $query
     * @param string $operator Comparison operator ('>', '<', '>=', '<=', '=', '!=')
     * @param int $minutes Duration in minutes
     * @return Builder
     */
    public function scopeByDuration(Builder $query, string $operator, int $minutes): Builder
    {
        $startField = $this->getStartTimeField();
        $endField = $this->getEndTimeField();

        // Using raw SQL to calculate duration in minutes
        $durationExpression = "TIMESTAMPDIFF(MINUTE, {$startField}, {$endField})";
        
        return $query->whereRaw("{$durationExpression} {$operator} ?", [$minutes]);
    }

    /**
     * Scope to order by start time.
     *
     * @param Builder $query
     * @param string $direction 'asc' or 'desc'
     * @return Builder
     */
    public function scopeOrderByStart(Builder $query, string $direction = 'asc'): Builder
    {
        $startField = $this->getStartTimeField();
        return $query->orderBy($startField, $direction);
    }

    /**
     * Scope to order by end time.
     *
     * @param Builder $query
     * @param string $direction 'asc' or 'desc'
     * @return Builder
     */
    public function scopeOrderByEnd(Builder $query, string $direction = 'asc'): Builder
    {
        $endField = $this->getEndTimeField();
        return $query->orderBy($endField, $direction);
    }

    /**
     * Scope to order by duration.
     *
     * @param Builder $query
     * @param string $direction 'asc' or 'desc'
     * @return Builder
     */
    public function scopeOrderByDuration(Builder $query, string $direction = 'asc'): Builder
    {
        $startField = $this->getStartTimeField();
        $endField = $this->getEndTimeField();

        // Using raw SQL to calculate and order by duration
        $durationExpression = "TIMESTAMPDIFF(MINUTE, {$startField}, {$endField})";
        
        return $query->orderByRaw("{$durationExpression} {$direction}");
    }

    /**
     * Get a human-readable time range display.
     * Handles single-day and multi-day ranges.
     */
    public function getTimeRangeAttribute(): string
    {
        $startField = $this->getStartTimeField();
        $endField = $this->getEndTimeField();
        
        if (! $this->{$startField} || ! $this->{$endField}) {
            return 'TBD';
        }

        if ($this->{$startField}->isSameDay($this->{$endField})) {
            return $this->{$startField}->format('M j, Y g:i A') . ' - ' . $this->{$endField}->format('g:i A');
        }

        return $this->{$startField}->format('M j, Y g:i A') . ' - ' . $this->{$endField}->format('M j, Y g:i A');
    }
}
