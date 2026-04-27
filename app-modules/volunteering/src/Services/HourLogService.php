<?php

namespace CorvMC\Volunteering\Services;

use App\Models\User;
use CorvMC\Volunteering\Events\HoursApproved;
use CorvMC\Volunteering\Events\HoursSubmitted;
use CorvMC\Volunteering\Events\VolunteerCheckedIn;
use CorvMC\Volunteering\Events\VolunteerCheckedOut;
use CorvMC\Volunteering\Events\VolunteerConfirmed;
use CorvMC\Volunteering\Events\VolunteerReleased;
use CorvMC\Volunteering\Models\HourLog;
use CorvMC\Volunteering\Models\Position;
use CorvMC\Volunteering\Models\Shift;
use CorvMC\Volunteering\States\HourLogState\CheckedIn;
use CorvMC\Volunteering\States\HourLogState\Confirmed;
use CorvMC\Volunteering\States\HourLogState\Interested;
use CorvMC\Volunteering\States\HourLogState\Pending;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class HourLogService
{
    // =========================================================================
    // Shift lifecycle
    // =========================================================================

    /**
     * Volunteer signs up for a shift. Creates an HourLog in Interested status.
     */
    public function signUp(User $user, Shift $shift): HourLog
    {
        if ($shift->start_at->isPast()) {
            throw new InvalidArgumentException('Cannot sign up for a shift that has already started.');
        }

        return DB::transaction(function () use ($user, $shift) {
            // Lock the shift row to prevent concurrent over-capacity signups
            $shift = Shift::lockForUpdate()->find($shift->id);

            if (! $shift->hasCapacity()) {
                throw new RuntimeException('This shift is full.');
            }

            $existing = HourLog::where('user_id', $user->id)
                ->where('shift_id', $shift->id)
                ->active()
                ->lockForUpdate()
                ->exists();

            if ($existing) {
                throw new RuntimeException('You already have an active sign-up for this shift.');
            }

            return HourLog::create([
                'user_id' => $user->id,
                'shift_id' => $shift->id,
                'status' => Interested::class,
            ]);
        });
    }

    /**
     * Staff confirms a volunteer for a shift.
     */
    public function confirm(HourLog $hourLog, User $reviewer): HourLog
    {
        return DB::transaction(function () use ($hourLog, $reviewer) {
            $hourLog->status->transitionTo(Confirmed::class);
            $hourLog->update(['reviewed_by' => $reviewer->id]);

            VolunteerConfirmed::dispatch($hourLog->fresh());

            return $hourLog->fresh();
        });
    }

    /**
     * Release a volunteer from a shift. Valid from Interested, Confirmed, or CheckedIn.
     */
    public function release(HourLog $hourLog, User $reviewer): HourLog
    {
        return DB::transaction(function () use ($hourLog, $reviewer) {
            $hourLog->status->transitionTo(\CorvMC\Volunteering\States\HourLogState\Released::class);
            $hourLog->update(['reviewed_by' => $reviewer->id]);

            VolunteerReleased::dispatch($hourLog->fresh());

            return $hourLog->fresh();
        });
    }

    /**
     * Check in a confirmed volunteer. Sets started_at to now.
     */
    public function checkIn(HourLog $hourLog): HourLog
    {
        return DB::transaction(function () use ($hourLog) {
            $hourLog->status->transitionTo(CheckedIn::class);
            $hourLog->update(['started_at' => now()]);

            VolunteerCheckedIn::dispatch($hourLog->fresh());

            return $hourLog->fresh();
        });
    }

    /**
     * Walk-in: create an HourLog directly in CheckedIn status.
     * For day-of volunteers who weren't pre-scheduled.
     */
    public function walkIn(User $user, Shift $shift): HourLog
    {
        return DB::transaction(function () use ($user, $shift) {
            $shift = Shift::lockForUpdate()->find($shift->id);

            if (! $shift->hasCapacity()) {
                throw new RuntimeException('This shift is full.');
            }

            $existing = HourLog::where('user_id', $user->id)
                ->where('shift_id', $shift->id)
                ->active()
                ->lockForUpdate()
                ->exists();

            if ($existing) {
                throw new RuntimeException('This volunteer already has an active sign-up for this shift.');
            }

            // Create directly in CheckedIn — no intermediate transitions
            $hourLog = HourLog::create([
                'user_id' => $user->id,
                'shift_id' => $shift->id,
                'status' => CheckedIn::class,
                'started_at' => now(),
            ]);

            VolunteerCheckedIn::dispatch($hourLog);

            return $hourLog;
        });
    }

    /**
     * Check out a volunteer. Sets ended_at to now and propagates tags.
     */
    public function checkOut(HourLog $hourLog): HourLog
    {
        return DB::transaction(function () use ($hourLog) {
            $hourLog->status->transitionTo(\CorvMC\Volunteering\States\HourLogState\CheckedOut::class);
            $hourLog->update(['ended_at' => now()]);

            // Propagate tags from Shift and Position onto the HourLog
            $shift = $hourLog->shift;
            if ($shift) {
                $hourLog->attachTags($shift->tags);

                if ($shift->position) {
                    $hourLog->attachTags($shift->position->tags);
                }
            }

            VolunteerCheckedOut::dispatch($hourLog->fresh());

            return $hourLog->fresh();
        });
    }

    // =========================================================================
    // Self-reported lifecycle
    // =========================================================================

    /**
     * Volunteer submits self-reported hours for review.
     */
    public function submitHours(User $user, array $data): HourLog
    {
        if (empty($data['position_id'])) {
            throw new InvalidArgumentException('position_id is required for self-reported hours.');
        }

        $position = Position::find($data['position_id']);
        if (! $position || $position->trashed()) {
            throw new InvalidArgumentException('Position does not exist or has been deleted.');
        }

        if (empty($data['started_at']) || empty($data['ended_at'])) {
            throw new InvalidArgumentException('started_at and ended_at are required.');
        }

        $startedAt = $data['started_at'];
        $endedAt = $data['ended_at'];

        if ($startedAt >= $endedAt) {
            throw new InvalidArgumentException('started_at must be before ended_at.');
        }

        if ($endedAt > now()) {
            throw new InvalidArgumentException('ended_at must be in the past.');
        }

        return DB::transaction(function () use ($user, $data) {
            $hourLog = HourLog::create([
                'user_id' => $user->id,
                'position_id' => $data['position_id'],
                'status' => Pending::class,
                'started_at' => $data['started_at'],
                'ended_at' => $data['ended_at'],
                'notes' => $data['notes'] ?? null,
            ]);

            HoursSubmitted::dispatch($hourLog);

            return $hourLog;
        });
    }

    /**
     * Staff approves self-reported hours. Propagates tags from Position.
     */
    public function approve(HourLog $hourLog, User $reviewer, array $tags = []): HourLog
    {
        return DB::transaction(function () use ($hourLog, $reviewer, $tags) {
            $hourLog->status->transitionTo(\CorvMC\Volunteering\States\HourLogState\Approved::class);
            $hourLog->update(['reviewed_by' => $reviewer->id]);

            // Propagate tags from Position onto the HourLog
            $position = $hourLog->position;
            if ($position) {
                $hourLog->attachTags($position->tags);
            }

            // Attach any reviewer-supplied tags
            if (! empty($tags)) {
                $hourLog->attachTags($tags);
            }

            HoursApproved::dispatch($hourLog->fresh());

            return $hourLog->fresh();
        });
    }

    /**
     * Staff rejects self-reported hours.
     */
    public function reject(HourLog $hourLog, User $reviewer, ?string $notes = null): HourLog
    {
        return DB::transaction(function () use ($hourLog, $reviewer, $notes) {
            $hourLog->status->transitionTo(\CorvMC\Volunteering\States\HourLogState\Rejected::class);

            $updates = ['reviewed_by' => $reviewer->id];
            if ($notes !== null) {
                $updates['notes'] = $notes;
            }
            $hourLog->update($updates);

            return $hourLog->fresh();
        });
    }
}
