<?php

namespace Database\Seeders;

use App\Models\User;
use Brick\Money\Money;
use Carbon\Carbon;
use CorvMC\Finance\Enums\ChargeStatus;
use CorvMC\Finance\Models\Charge;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\Support\Enums\RecurringSeriesStatus;
use CorvMC\Support\Models\RecurringSeries;
use Illuminate\Database\Seeder;

class ReservationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();

        if ($users->isEmpty()) {
            $this->command->warn('No users found. Please run MemberProfileSeeder first.');

            return;
        }

        // Create reservations for the current week and next few weeks
        $this->createWeeklyReservations($users);

        // Create some recurring reservations
        $this->createRecurringReservations($users);

        // Create some past reservations for testing
        $this->createPastReservations($users);
    }

    private function createWeeklyReservations($users): void
    {
        $startDate = Carbon::now()->startOfWeek();

        // Create reservations for the next 4 weeks
        for ($week = 0; $week < 4; $week++) {
            $weekStart = $startDate->copy()->addWeeks($week);

            // Monday through Sunday
            for ($day = 0; $day < 7; $day++) {
                $date = $weekStart->copy()->addDays($day);

                // Skip past dates for current week
                if ($date->isPast()) {
                    continue;
                }

                // More realistic distribution based on day of week
                $reservationCount = $this->getReservationCountForDay($date);

                for ($i = 0; $i < $reservationCount; $i++) {
                    $this->createDailyReservation($users->random(), $date);
                }
            }
        }
    }

    private function getReservationCountForDay(Carbon $date): int
    {
        // More realistic booking patterns
        return match ($date->dayOfWeek) {
            Carbon::MONDAY => rand(0, 2),    // Light Monday
            Carbon::TUESDAY => rand(1, 3),   // Moderate Tuesday
            Carbon::WEDNESDAY => rand(2, 4), // Popular mid-week
            Carbon::THURSDAY => rand(2, 4),  // Popular Thursday
            Carbon::FRIDAY => rand(3, 5),    // Busy Friday
            Carbon::SATURDAY => rand(2, 4),  // Weekend activity
            Carbon::SUNDAY => rand(0, 2),    // Light Sunday
        };
    }

    private function createDailyReservation(User $user, Carbon $date): void
    {
        // More realistic time distribution - weighted toward popular hours
        $popularHours = [
            10 => 2, 11 => 3, 12 => 2, // Morning slots (lower weight)
            13 => 3, 14 => 4, 15 => 4, 16 => 4, // Afternoon (moderate)
            17 => 5, 18 => 6, 19 => 6, 20 => 4, // Evening peak (higher weight)
            21 => 2, // Late evening (lower)
        ];

        $weightedHours = [];
        foreach ($popularHours as $hour => $weight) {
            for ($i = 0; $i < $weight; $i++) {
                $weightedHours[] = $hour;
            }
        }

        $startHour = $weightedHours[array_rand($weightedHours)];
        $startMinute = [0, 15, 30, 45][rand(0, 3)]; // Quarter-hour intervals

        $startTime = $date->copy()->setTime($startHour, $startMinute);

        // More realistic duration distribution - most people book 2-3 hours
        $durationWeights = [1 => 1, 2 => 4, 3 => 3, 4 => 1]; // Weighted toward 2-3 hours
        $durations = [];
        foreach ($durationWeights as $duration => $weight) {
            for ($i = 0; $i < $weight; $i++) {
                $durations[] = $duration;
            }
        }

        $durationHours = $durations[array_rand($durations)];
        $endTime = $startTime->copy()->addHours($durationHours);

        // Don't create if it goes past 10 PM
        if ($endTime->hour > 22) {
            return;
        }

        // Check if time slot conflicts with existing reservations
        $conflicts = RehearsalReservation::where('reserved_until', '>', $startTime)
            ->where('reserved_at', '<', $endTime)
            ->where('status', '!=', 'cancelled')
            ->exists();

        if ($conflicts) {
            return; // Skip this reservation
        }

        // Create the reservation
        $reservation = RehearsalReservation::factory()->create([
            'reservable_type' => 'user',
            'reservable_id' => $user->id,
            'reserved_at' => $startTime,
            'reserved_until' => $endTime,
            'status' => $this->getRandomStatus(),
            'notes' => $this->getRandomNotes(),
        ]);

        $this->createChargeForReservation($reservation, $user);
    }

    private function createRecurringReservations($users): void
    {
        // Get sustaining members (users with the role)
        $sustainingMembers = $users->filter(function ($user) {
            return $user->hasRole('sustaining member');
        });

        if ($sustainingMembers->isEmpty()) {
            return;
        }

        $seriesConfigs = [
            [
                'day' => 'Tuesday',
                'start_hour' => 19,
                'duration' => 2,
                'rule' => 'FREQ=WEEKLY;BYDAY=TU',
                'end_date' => null, // Ongoing
                'notes' => 'Weekly band practice - Tuesdays (ongoing)',
                'status' => RecurringSeriesStatus::ACTIVE,
            ],
            [
                'day' => 'Thursday',
                'start_hour' => 18,
                'duration' => 3,
                'rule' => 'FREQ=WEEKLY;BYDAY=TH;COUNT=12',
                'end_date' => fn ($start) => $start->copy()->addWeeks(12),
                'notes' => 'Thursday evening rehearsal (12 weeks)',
                'status' => RecurringSeriesStatus::ACTIVE,
            ],
            [
                'day' => 'Saturday',
                'start_hour' => 14,
                'duration' => 2,
                'rule' => 'FREQ=WEEKLY;BYDAY=SA',
                'end_date' => null,
                'notes' => 'Weekend jam session (paused)',
                'status' => RecurringSeriesStatus::PAUSED,
            ],
        ];

        foreach ($seriesConfigs as $index => $config) {
            if ($index >= $sustainingMembers->count()) {
                break;
            }

            $user = $sustainingMembers->values()->get($index);
            $seriesStartDate = Carbon::now()->next($config['day']);

            // Create the recurring series record
            $endDate = is_callable($config['end_date'])
                ? $config['end_date']($seriesStartDate)
                : $config['end_date'];

            $series = RecurringSeries::create([
                'user_id' => $user->id,
                'recurable_type' => 'rehearsal_reservation',
                'recurrence_rule' => $config['rule'],
                'start_time' => sprintf('%02d:00:00', $config['start_hour']),
                'end_time' => sprintf('%02d:00:00', $config['start_hour'] + $config['duration']),
                'series_start_date' => $seriesStartDate,
                'series_end_date' => $endDate,
                'max_advance_days' => 14,
                'status' => $config['status'],
                'notes' => $config['notes'],
            ]);

            // Only generate instances for active series
            if ($config['status'] === RecurringSeriesStatus::ACTIVE) {
                // Create 4 weeks of instances
                for ($week = 0; $week < 4; $week++) {
                    $instanceDate = $seriesStartDate->copy()->addWeeks($week);
                    $startTime = $instanceDate->copy()->setTime($config['start_hour'], 0);
                    $endTime = $startTime->copy()->addHours($config['duration']);

                    // Check for conflicts
                    $conflicts = RehearsalReservation::where('reserved_until', '>', $startTime)
                        ->where('reserved_at', '<', $endTime)
                        ->where('status', '!=', 'cancelled')
                        ->exists();

                    if ($conflicts) {
                        continue;
                    }

                    $reservation = RehearsalReservation::factory()->create([
                        'reservable_type' => 'user',
                        'reservable_id' => $user->id,
                        'reserved_at' => $startTime,
                        'reserved_until' => $endTime,
                        'status' => 'confirmed',
                        'is_recurring' => true,
                        'recurring_series_id' => $series->id,
                        'instance_date' => $instanceDate->toDateString(),
                        'notes' => $config['notes'],
                    ]);

                    $this->createChargeForReservation($reservation, $user);
                }
            }
        }
    }

    private function createPastReservations($users): void
    {
        // Create some past reservations for the last 2 weeks
        $startDate = Carbon::now()->subWeeks(2)->startOfWeek();

        for ($week = 0; $week < 2; $week++) {
            $weekStart = $startDate->copy()->addWeeks($week);

            for ($day = 0; $day < 7; $day++) {
                $date = $weekStart->copy()->addDays($day);

                // Use same realistic distribution for past days
                $reservationCount = $this->getReservationCountForDay($date);

                for ($i = 0; $i < $reservationCount; $i++) {
                    $this->createPastReservation($users->random(), $date);
                }
            }
        }
    }

    private function createPastReservation(User $user, Carbon $date): void
    {
        // Use same realistic time distribution for past reservations
        $popularHours = [
            10 => 2, 11 => 3, 12 => 2,
            13 => 3, 14 => 4, 15 => 4, 16 => 4,
            17 => 5, 18 => 6, 19 => 6, 20 => 4,
            21 => 2,
        ];

        $weightedHours = [];
        foreach ($popularHours as $hour => $weight) {
            for ($i = 0; $i < $weight; $i++) {
                $weightedHours[] = $hour;
            }
        }

        $startHour = $weightedHours[array_rand($weightedHours)];
        $startMinute = [0, 15, 30, 45][rand(0, 3)];
        $startTime = $date->copy()->setTime($startHour, $startMinute);

        $durationWeights = [1 => 1, 2 => 4, 3 => 3, 4 => 1];
        $durations = [];
        foreach ($durationWeights as $duration => $weight) {
            for ($i = 0; $i < $weight; $i++) {
                $durations[] = $duration;
            }
        }

        $durationHours = $durations[array_rand($durations)];
        $endTime = $startTime->copy()->addHours($durationHours);

        if ($endTime->hour > 22) {
            return;
        }

        $reservation = RehearsalReservation::factory()->create([
            'reservable_type' => 'user',
            'reservable_id' => $user->id,
            'reserved_at' => $startTime,
            'reserved_until' => $endTime,
            'status' => 'confirmed', // Past reservations are typically confirmed
            'notes' => $this->getRandomNotes(),
        ]);

        $this->createChargeForReservation($reservation, $user, true);
    }

    private function createChargeForReservation(RehearsalReservation $reservation, User $user, bool $isPast = false): void
    {
        $hourlyRate = 1500; // $15/hour in cents
        // Use floatDiffInHours for accurate calculation, then round up
        $duration = (int) ceil($reservation->reserved_at->floatDiffInHours($reservation->reserved_until));
        $amount = $hourlyRate * $duration;

        // Sustaining members get free hours
        $isSustaining = $user->hasRole('sustaining member');
        $freeHoursUsed = 0;
        $netAmount = $amount;

        if ($isSustaining && $duration <= 4) {
            // Use free hours for sustaining members (up to 4 hours)
            $freeHoursUsed = $duration;
            $netAmount = 0;
        }

        $status = ChargeStatus::Pending;
        $paidAt = null;
        $paymentMethod = null;

        // Past or confirmed reservations should have settled charges
        if ($isPast || $reservation->status === 'confirmed') {
            if ($netAmount === 0) {
                $status = ChargeStatus::Paid;
                $paymentMethod = 'credits';
                $paidAt = $reservation->created_at;
            } else {
                // Randomly mark as paid or pending for variety
                if (rand(0, 10) > 2) {
                    $status = ChargeStatus::Paid;
                    $paymentMethod = collect(['stripe', 'cash', 'manual'])->random();
                    $paidAt = $reservation->created_at;
                }
            }
        }

        Charge::create([
            'user_id' => $user->id,
            'chargeable_type' => $reservation->getMorphClass(),
            'chargeable_id' => $reservation->id,
            'amount' => Money::ofMinor($amount, 'USD'),
            'credits_applied' => $freeHoursUsed > 0 ? ['free_hours' => $freeHoursUsed * 2] : null,
            'net_amount' => Money::ofMinor($netAmount, 'USD'),
            'status' => $status,
            'payment_method' => $paymentMethod,
            'paid_at' => $paidAt,
        ]);

        // Update reservation's free_hours_used
        if ($freeHoursUsed > 0) {
            $reservation->updateQuietly(['free_hours_used' => $freeHoursUsed]);
        }
    }

    private function getRandomStatus(): string
    {
        return collect(['confirmed', 'pending', 'confirmed', 'confirmed'])
            ->random(); // Bias toward confirmed reservations
    }

    private function getRandomNotes(): ?string
    {
        $notes = [
            null,
            'Band practice session',
            'Solo practice time',
            'Recording session',
            'Rehearsal for upcoming show',
            'Acoustic practice',
            'Drum practice',
            'Collaborative session',
            'Songwriting session',
        ];

        return collect($notes)->random();
    }
}
