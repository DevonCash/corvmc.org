# Recurring Reservations System Design

**Status:** Design Proposal
**Created:** October 1, 2025
**Purpose:** Redesign recurring reservations with parent-child relationship for better tracking and management

## Problem Statement

Current recurring reservation implementation creates independent reservations in a batch, which has issues:

- **No ongoing record**: Once created, there's no "series" to reference
- **Can't modify series**: Must manually update each individual reservation
- **Can't cancel series**: Must cancel each reservation individually
- **Can't extend**: If user wants to extend 6 weeks → 12 weeks, must create new batch
- **Hard to track**: No way to see "this user has a standing Tuesday slot"
- **No future generation**: All reservations created upfront, can't generate as needed

## Proposed Solution: Parent-Child Recurring Reservations

### Core Concept
Separate the **recurring pattern** (parent) from **individual instances** (children).

### Use Cases

**Example 1: Band Rehearsal**
- Pattern: "Every Tuesday, 7-9 PM, starting Nov 5, for 6 months"
- Generates: Individual reservations for each Tuesday
- User can: Cancel one instance (sick), cancel whole series, extend series

**Example 2: Monthly Committee Meeting**
- Pattern: "First Monday of each month, 6-8 PM, indefinite"
- Generates: Reservations monthly as dates approach
- User can: Skip one month, end the series

## Database Schema

```sql
-- Parent: The recurring pattern
CREATE TABLE recurring_reservations (
    id BIGINT PRIMARY KEY,
    user_id BIGINT NOT NULL,

    -- Pattern definition
    recurrence_rule VARCHAR(255) NOT NULL, -- iCal RRULE format (flexible)
    start_time TIME NOT NULL, -- e.g., '19:00:00'
    end_time TIME NOT NULL, -- e.g., '21:00:00'
    duration_minutes INTEGER NOT NULL, -- Redundant but helpful

    -- Series metadata
    series_start_date DATE NOT NULL, -- When series begins
    series_end_date DATE NULL, -- NULL = indefinite, or specific end date
    max_advance_days INTEGER DEFAULT 90, -- How far ahead to generate instances (e.g., 90 days)

    -- Status
    status VARCHAR(20) DEFAULT 'active', -- 'active', 'paused', 'cancelled', 'completed'

    -- Notes
    notes TEXT NULL,

    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX(user_id, status),
    INDEX(status, series_end_date)
);

-- Child: Individual reservation instances (combined into reservations table)
-- Existing reservations table gets new fields:
ALTER TABLE reservations ADD COLUMN recurring_reservation_id BIGINT NULL;
ALTER TABLE reservations ADD COLUMN instance_date DATE NULL; -- The date this instance represents in the series
ALTER TABLE reservations ADD COLUMN cancellation_reason VARCHAR(100) NULL; -- Reason for cancellation (replaces appending to notes)

ALTER TABLE reservations ADD FOREIGN KEY (recurring_reservation_id) REFERENCES recurring_reservations(id) ON DELETE SET NULL;
ALTER TABLE reservations ADD INDEX(recurring_reservation_id, instance_date);
ALTER TABLE reservations ADD INDEX(instance_date);

-- Note: We track skipped instances by having reservations with status='cancelled' and cancellation_reason like:
--   'Manually skipped' - User chose to skip this instance
--   'Scheduling conflict' - Conflict detected during generation
--   'Recurring series cancelled' - Entire series was cancelled
--   Or any user-provided reason for manual cancellations
```

## Recurrence Rule Format

Use **iCal RRULE** format (industry standard, well-documented):

```
FREQ=WEEKLY;INTERVAL=1;BYDAY=TU
```

**Examples:**
- Every Tuesday: `FREQ=WEEKLY;BYDAY=TU`
- Every other Monday: `FREQ=WEEKLY;INTERVAL=2;BYDAY=MO`
- First Friday of month: `FREQ=MONTHLY;BYDAY=1FR`
- Every weekday: `FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR`

**Why RRULE?**
- Industry standard (Google Calendar, Outlook use this)
- Well-tested parsing libraries exist
- Flexible (supports complex patterns)
- Human-readable with proper formatting

## Service Layer

```php
class RecurringReservationService
{
    /**
     * Create a new recurring reservation series.
     */
    public function createRecurringSeries(
        User $user,
        string $recurrenceRule,
        Carbon $startDate,
        string $startTime, // '19:00'
        string $endTime,   // '21:00'
        ?Carbon $endDate = null,
        int $maxAdvanceDays = 90,
        ?string $notes = null
    ): RecurringReservation {
        if (!$user->isSustainingMember()) {
            throw new \InvalidArgumentException('Only sustaining members can create recurring reservations.');
        }

        $series = RecurringReservation::create([
            'user_id' => $user->id,
            'recurrence_rule' => $recurrenceRule,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'duration_minutes' => Carbon::parse($startTime)->diffInMinutes(Carbon::parse($endTime)),
            'series_start_date' => $startDate,
            'series_end_date' => $endDate,
            'max_advance_days' => $maxAdvanceDays,
            'status' => 'active',
            'notes' => $notes,
        ]);

        // Generate initial instances
        $this->generateInstances($series);

        return $series;
    }

    /**
     * Generate reservation instances for a recurring series.
     * Only generates up to max_advance_days into the future.
     */
    public function generateInstances(RecurringReservation $series): Collection
    {
        $rule = $this->parseRecurrenceRule($series->recurrence_rule);
        $startDate = $series->series_start_date;
        $maxDate = now()->addDays($series->max_advance_days);

        if ($series->series_end_date && $series->series_end_date->lt($maxDate)) {
            $maxDate = $series->series_end_date;
        }

        $occurrences = $this->calculateOccurrences($rule, $startDate, $maxDate);
        $created = collect();

        foreach ($occurrences as $date) {
            // Check if instance already exists
            $existing = Reservation::where('recurring_reservation_id', $series->id)
                ->where('instance_date', $date)
                ->first();

            // Skip if already exists
            if ($existing) {
                continue;
            }

            // Try to create the actual reservation
            try {
                $reservation = $this->createInstanceReservation($series, $date);
                $created->push($reservation);
            } catch (\InvalidArgumentException $e) {
                // Conflict - create a placeholder cancelled reservation to track skip
                Reservation::create([
                    'user_id' => $series->user_id,
                    'recurring_reservation_id' => $series->id,
                    'instance_date' => $date,
                    'reserved_at' => $date->copy()->setTimeFromTimeString($series->start_time),
                    'reserved_until' => $date->copy()->setTimeFromTimeString($series->end_time),
                    'status' => 'cancelled',
                    'cancellation_reason' => 'Scheduling conflict',
                    'is_recurring' => true,
                ]);
            }
        }

        return $created;
    }

    /**
     * Create a single reservation instance from recurring pattern.
     */
    protected function createInstanceReservation(
        RecurringReservation $series,
        Carbon $date
    ): Reservation {
        $startDateTime = $date->copy()->setTimeFromTimeString($series->start_time);
        $endDateTime = $date->copy()->setTimeFromTimeString($series->end_time);

        return ReservationService::createReservation(
            $series->user,
            $startDateTime,
            $endDateTime,
            [
                'recurring_reservation_id' => $series->id,
                'instance_date' => $date,
                'is_recurring' => true,
                'recurrence_pattern' => ['source' => 'recurring_series'],
                'status' => 'pending', // Require confirmation
            ]
        );
    }

    /**
     * Calculate occurrence dates from recurrence rule.
     */
    protected function calculateOccurrences(array $rule, Carbon $start, Carbon $end): array
    {
        // Use library like spatie/icalendar-generator or recurr/recurr
        // For MVP, support basic patterns:

        $freq = $rule['FREQ'] ?? 'WEEKLY';
        $interval = $rule['INTERVAL'] ?? 1;
        $byDay = $rule['BYDAY'] ?? [];

        $occurrences = [];
        $current = $start->copy();

        while ($current->lte($end)) {
            if ($this->matchesRule($current, $rule)) {
                $occurrences[] = $current->copy();
            }

            // Advance by frequency
            if ($freq === 'WEEKLY') {
                $current->addWeeks($interval);
            } elseif ($freq === 'MONTHLY') {
                $current->addMonths($interval);
            }
        }

        return $occurrences;
    }

    /**
     * Cancel entire recurring series.
     */
    public function cancelSeries(RecurringReservation $series, ?string $reason = null): void
    {
        DB::transaction(function () use ($series, $reason) {
            // Cancel series
            $series->update(['status' => 'cancelled']);

            // Cancel all future instances
            $futureReservations = Reservation::where('recurring_reservation_id', $series->id)
                ->where('reserved_at', '>', now())
                ->whereIn('status', ['pending', 'confirmed'])
                ->get();

            foreach ($futureReservations as $reservation) {
                $reservation->update([
                    'status' => 'cancelled',
                    'cancellation_reason' => $reason ?? 'Recurring series cancelled',
                ]);
            }
        });
    }

    /**
     * Skip a single instance without cancelling series.
     */
    public function skipInstance(RecurringReservation $series, Carbon $date): void
    {
        $reservation = Reservation::where('recurring_reservation_id', $series->id)
            ->where('instance_date', $date->toDateString())
            ->first();

        DB::transaction(function () use ($reservation, $series, $date) {
            if ($reservation) {
                // Cancel existing reservation
                $reservation->update([
                    'status' => 'cancelled',
                    'cancellation_reason' => 'Manually skipped',
                ]);
            } else {
                // Create placeholder cancelled reservation to track manual skip
                Reservation::create([
                    'user_id' => $series->user_id,
                    'recurring_reservation_id' => $series->id,
                    'instance_date' => $date,
                    'reserved_at' => $date->copy()->setTimeFromTimeString($series->start_time),
                    'reserved_until' => $date->copy()->setTimeFromTimeString($series->end_time),
                    'status' => 'cancelled',
                    'cancellation_reason' => 'Manually skipped',
                    'is_recurring' => true,
                ]);
            }
        });
    }

    /**
     * Extend series end date.
     */
    public function extendSeries(RecurringReservation $series, Carbon $newEndDate): void
    {
        $series->update(['series_end_date' => $newEndDate]);

        // Generate new instances
        $this->generateInstances($series);
    }

    /**
     * Get upcoming instances for a series.
     */
    public function getUpcomingInstances(RecurringReservation $series, int $limit = 10): Collection
    {
        return Reservation::where('recurring_reservation_id', $series->id)
            ->where('instance_date', '>=', now()->toDateString())
            ->orderBy('instance_date')
            ->limit($limit)
            ->get();
    }

    /**
     * Scheduled job: Generate future instances for all active series.
     */
    public function generateFutureInstances(): void
    {
        $activeSeries = RecurringReservation::where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('series_end_date')
                  ->orWhere('series_end_date', '>', now());
            })
            ->get();

        foreach ($activeSeries as $series) {
            $this->generateInstances($series);
        }
    }
}
```

## Scheduled Jobs

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    // Generate instances daily for active recurring reservations
    $schedule->call(function () {
        app(RecurringReservationService::class)->generateFutureInstances();
    })->daily();
}
```

## Filament UI Integration

### Create Recurring Series Form

```php
// Option 1: Separate resource for recurring reservations
Filament\Resources\RecurringReservationResource

// Option 2: Toggle on existing reservation form
Toggle::make('create_recurring')
    ->label('Make this a recurring reservation?')
    ->visible(fn() => auth()->user()->isSustainingMember())
    ->reactive()

Section::make('Recurrence Pattern')
    ->visible(fn(Get $get) => $get('create_recurring'))
    ->schema([
        Select::make('frequency')
            ->options([
                'WEEKLY' => 'Weekly',
                'MONTHLY' => 'Monthly',
            ])
            ->default('WEEKLY'),

        Select::make('interval')
            ->label('Repeat every')
            ->options([
                1 => '1',
                2 => '2',
                3 => '3',
                4 => '4',
            ])
            ->suffix(fn(Get $get) => $get('frequency') === 'WEEKLY' ? 'week(s)' : 'month(s)')
            ->default(1),

        CheckboxList::make('by_day')
            ->label('On days')
            ->options([
                'MO' => 'Monday',
                'TU' => 'Tuesday',
                'WE' => 'Wednesday',
                'TH' => 'Thursday',
                'FR' => 'Friday',
                'SA' => 'Saturday',
                'SU' => 'Sunday',
            ])
            ->visible(fn(Get $get) => $get('frequency') === 'WEEKLY'),

        DatePicker::make('series_end_date')
            ->label('End date (optional)')
            ->hint('Leave blank for ongoing reservation'),

        Placeholder::make('preview')
            ->label('Preview upcoming dates')
            ->content(fn(Get $get) => $this->previewDates($get)),
    ])
```

### View Recurring Series

```php
// Show series with all instances
Infolist::make()
    ->schema([
        TextEntry::make('recurrence_rule')
            ->label('Pattern')
            ->formatStateUsing(fn($state) => $this->formatRule($state)),

        TextEntry::make('status')
            ->badge(),

        RepeatableEntry::make('instances')
            ->label('Upcoming Reservations')
            ->schema([
                TextEntry::make('instance_date'),
                TextEntry::make('reservation.status')
                    ->badge(),
                Actions::make([
                    Action::make('skip')
                        ->icon('tabler-x')
                        ->requiresConfirmation(),
                ]),
            ])
            ->getStateUsing(fn($record) => $record->instances()->upcoming()->get()),
    ])
```

## Benefits

### Immediate
- ✅ **Clear ownership**: Each series is a distinct entity
- ✅ **Easy management**: Cancel/modify entire series at once
- ✅ **Conflict tracking**: Know which instances were skipped and why
- ✅ **Future generation**: Create instances as needed, not all upfront
- ✅ **Better UX**: Users see "your recurring Tuesday slot" not "40 separate bookings"

### Long-term
- 📊 **Better reporting**: "How many standing reservations exist?"
- 🔧 **Easier modifications**: Change series time, extend duration
- 📅 **Calendar integration**: Export series as single iCal event
- 💰 **Billing clarity**: Bill monthly for recurring series vs per-instance
- 🎯 **Capacity planning**: See recurring commitments vs one-offs
- 🔔 **Notifications**: "Your recurring reservation starts next week"

## Migration Strategy

**Pre-deployment**: No existing recurring reservations to migrate.

### Implementation Plan

1. **Create new tables** (1-2 hours)
   - `recurring_reservations`
   - `recurring_reservation_instances`
   - Add `recurring_reservation_id` to `reservations`

2. **Build RecurringReservationService** (4-6 hours)
   - RRULE parsing (use library)
   - Instance generation logic
   - Cancel/skip/extend methods
   - Scheduled job for generation

3. **Remove old recurring logic** (1 hour)
   - Remove `createRecurringReservation()` from ReservationService
   - Keep `is_recurring` field for backward compatibility

4. **Filament UI** (3-4 hours)
   - Create RecurringReservationResource OR
   - Add toggle to existing form
   - Instance preview
   - Series management views

5. **Models** (1 hour)
   - RecurringReservation model
   - RecurringReservationInstance model
   - Relationships

6. **Testing** (2-3 hours)
   - Test instance generation
   - Test conflict skipping
   - Test series cancellation
   - Test scheduled job

**Total: 12-17 hours**

## Design Decisions

1. **RRULE vs Simple Pattern**: Use RRULE for flexibility and standards compliance
2. **Max Advance Days**: Generate instances 90 days ahead by default (configurable per series)
3. **Instance Tracking**: Track all potential instances, even if skipped
4. **Status**: Series-level status + per-instance reservation status
5. **Indefinite Series**: Allow NULL end_date for ongoing reservations
6. **Scheduled Generation**: Daily job generates new instances as dates approach

## Open Questions

1. **Payment handling**: Bill per-instance or monthly for series?
   - Proposal: Per-instance for MVP, add monthly billing later

2. **Series modifications**: If user changes pattern, regenerate all future instances?
   - Proposal: Yes, cancel pending instances and regenerate

3. **Conflict resolution**: Auto-skip conflicts or notify user?
   - Proposal: Auto-skip and mark, notify user of skips

4. **Max series length**: Limit how far into future?
   - Proposal: 6 months max for MVP

## Success Metrics

- Users can create recurring reservations via UI
- Instances generate automatically via scheduled job
- Series can be cancelled/extended as single operation
- Admin can see all recurring commitments
- Clear audit trail of skipped instances

## References

- iCal RFC 5545: https://icalendar.org/iCalendar-RFC-5545/3-3-10-recurrence-rule.html
- PHP RRULE library: https://github.com/simshaun/recurr
- Spatie Period library (already in use)
