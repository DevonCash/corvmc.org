<?php

namespace Database\Seeders;

use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
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
        $conflicts = Reservation::where('reserved_until', '>', $startTime)
            ->where('reserved_at', '<', $endTime)
            ->where('status', '!=', 'cancelled')
            ->exists();

        if ($conflicts) {
            return; // Skip this reservation
        }

        // Create the reservation
        Reservation::factory()->create([
            'reservable_type' => User::class,
            'reservable_id' => $user->id,
            'reserved_at' => $startTime,
            'reserved_until' => $endTime,
            'status' => $this->getRandomStatus(),
            'notes' => $this->getRandomNotes(),
        ]);
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

        // Create 2-3 recurring reservations
        for ($i = 0; $i < min(3, $sustainingMembers->count()); $i++) {
            $user = $sustainingMembers->random();

            // Every week for 4 weeks, same time slot
            $startTime = Carbon::now()->next('Wednesday')->setTime(19, 0); // Wednesday 7 PM
            $endTime = $startTime->copy()->addHours(2); // 2 hour sessions

            for ($week = 0; $week < 4; $week++) {
                $weeklyStart = $startTime->copy()->addWeeks($week);
                $weeklyEnd = $endTime->copy()->addWeeks($week);

                Reservation::factory()->create([
                    'reservable_type' => User::class,
                    'reservable_id' => $user->id,
                    'reserved_at' => $weeklyStart,
                    'reserved_until' => $weeklyEnd,
                    'status' => 'confirmed',
                    'is_recurring' => true,
                    'recurrence_pattern' => [
                        'weeks' => 4,
                        'interval' => 1,
                    ],
                    'notes' => 'Weekly band practice session',
                ]);
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

        Reservation::factory()->create([
            'reservable_type' => User::class,
            'reservable_id' => $user->id,
            'reserved_at' => $startTime,
            'reserved_until' => $endTime,
            'status' => 'confirmed', // Past reservations are typically confirmed
            'notes' => $this->getRandomNotes(),
        ]);
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
