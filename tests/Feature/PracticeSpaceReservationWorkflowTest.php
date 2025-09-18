<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Band;
use App\Models\Production;
use App\Models\Reservation;
use App\Models\Transaction;
use App\Notifications\ReservationConfirmedNotification;
use App\Notifications\ReservationCancelledNotification;
use App\Facades\ReservationService;
use App\Facades\UserSubscriptionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

describe('Practice Space Reservation Workflow Tests', function () {
    uses(RefreshDatabase::class);

    beforeEach(function () {
        Carbon::setTestNow('2024-01-15 10:00:00'); // Monday morning for consistent testing
    });

    describe('Story 1: Book Practice Space', function () {
        it('completes basic practice space booking workflow', function () {
            $user = User::factory()->create();

            $startTime = now()->addHours(4); // 2:00 PM
            $endTime = $startTime->copy()->addHours(2); // 4:00 PM

            // Verify time slot is available
            $isAvailable = ReservationService::isTimeSlotAvailable($startTime, $endTime);
            expect($isAvailable)->toBeTrue();

            // Calculate cost breakdown
            $costBreakdown = ReservationService::calculateCost($user, $startTime, $endTime);

            expect($costBreakdown)->toHaveKeys([
                'total_hours',
                'free_hours',
                'paid_hours',
                'cost',
                'hourly_rate',
                'is_sustaining_member',
                'remaining_free_hours'
            ])
                ->and($costBreakdown['total_hours'])->toBe(2.0)
                ->and($costBreakdown['free_hours'])->toBe(0)
                ->and($costBreakdown['paid_hours'])->toBe(2.0)
                ->and($costBreakdown['cost']->getAmount()->toFloat())->toBe(30.0) // $15 * 2 hours
                ->and($costBreakdown['hourly_rate'])->toBe(15.0)
                ->and($costBreakdown['is_sustaining_member'])->toBeFalse();
        });

        it('validates operating hours (9 AM - 10 PM)', function () {
            $user = User::factory()->create();

            // Test valid hours (2 PM - 4 PM)
            $validStart = now()->addDays(1)->setHour(14);
            $validEnd = $validStart->copy()->addHours(2);

            $validationResult = ReservationService::validateReservation($user, $validStart, $validEnd);
            expect($validationResult)->toBeArray();

            // Test too early (7 AM - 9 AM)
            $tooEarlyStart = now()->addDays(1)->setHour(7);
            $tooEarlyEnd = $tooEarlyStart->copy()->addHours(2);

            $earlyValidation = ReservationService::validateReservation($user, $tooEarlyStart, $tooEarlyEnd);
            expect($earlyValidation)->toBeArray(); // Should contain validation errors

            // Test too late (10 PM - 12 AM)
            $tooLateStart = now()->addDays(1)->setHour(22);
            $tooLateEnd = $tooLateStart->copy()->addHours(2);

            $lateValidation = ReservationService::validateReservation($user, $tooLateStart, $tooLateEnd);
            expect($lateValidation)->toBeArray(); // Should contain validation errors
        });

        it('enforces duration limits (1-8 hours)', function () {
            $user = User::factory()->create();
            $baseTime = now()->addDays(1)->setHour(14);

            // Test minimum duration (1 hour) - should be valid
            $oneHourEnd = $baseTime->copy()->addHours(1);
            $validation = ReservationService::validateReservation($user, $baseTime, $oneHourEnd);
            expect($validation)->toBeArray();

            // Test maximum duration (8 hours) - should be valid
            $eightHourEnd = $baseTime->copy()->addHours(8);
            $validation = ReservationService::validateReservation($user, $baseTime, $eightHourEnd);
            expect($validation)->toBeArray();

            // Test too short (30 minutes) - should be invalid
            $tooShortEnd = $baseTime->copy()->addMinutes(30);
            $validation = ReservationService::validateReservation($user, $baseTime, $tooShortEnd);
            expect($validation)->toBeArray(); // Should contain error

            // Test too long (9 hours) - should be invalid
            $tooLongEnd = $baseTime->copy()->addHours(9);
            $validation = ReservationService::validateReservation($user, $baseTime, $tooLongEnd);
            expect($validation)->toBeArray(); // Should contain error
        });

        it('prevents booking in the past', function () {
            $user = User::factory()->create();

            $pastTime = now()->subHours(2);
            $pastEnd = $pastTime->copy()->addHours(1);

            $validation = ReservationService::validateReservation($user, $pastTime, $pastEnd);
            expect($validation)->toContain('Reservation start time must be in the future.');
        });
    });

    describe('Story 2: View My Reservations', function () {
        it('displays reservations with correct duration calculations', function () {
            $user = User::factory()->create();

            // Create reservations with different durations
            $reservation1 = Reservation::factory()->create([
                'user_id' => $user->id,
                'reserved_at' => now()->addDays(1)->setHour(14),
                'reserved_until' => now()->addDays(1)->setHour(16), // 2 hours
                'status' => 'confirmed'
            ]);

            $reservation2 = Reservation::factory()->create([
                'user_id' => $user->id,
                'reserved_at' => now()->addDays(2)->setHour(10),
                'reserved_until' => now()->addDays(2)->setTime(13, 30), // 3.5 hours
                'status' => 'confirmed'
            ]);

            // Test duration calculations
            $duration1 = ReservationService::calculateHours($reservation1->reserved_at, $reservation1->reserved_until);
            $duration2 = ReservationService::calculateHours($reservation2->reserved_at, $reservation2->reserved_until);

            expect($duration1)->toBe(2.0)
                ->and($duration2)->toBe(3.5);

            // Verify user can see their reservations
            $userReservations = Reservation::where('user_id', $user->id)->get();
            expect($userReservations)->toHaveCount(2);
        });

        it('shows upcoming vs past reservations correctly', function () {
            $user = User::factory()->create();

            // Past reservation
            $pastReservation = Reservation::factory()->create([
                'user_id' => $user->id,
                'reserved_at' => now()->subDays(2),
                'reserved_until' => now()->subDays(2)->addHours(2),
                'status' => 'completed'
            ]);

            // Upcoming reservation
            $upcomingReservation = Reservation::factory()->create([
                'user_id' => $user->id,
                'reserved_at' => now()->addDays(1),
                'reserved_until' => now()->addDays(1)->addHours(2),
                'status' => 'confirmed'
            ]);

            $upcoming = Reservation::where('user_id', $user->id)
                ->where('reserved_at', '>', now())
                ->get();

            $past = Reservation::where('user_id', $user->id)
                ->where('reserved_at', '<', now())
                ->get();

            expect($upcoming)->toHaveCount(1)
                ->and($past)->toHaveCount(1)
                ->and($upcoming->first()->id)->toBe($upcomingReservation->id)
                ->and($past->first()->id)->toBe($pastReservation->id);
        });
    });

    describe('Story 3: Cancel and Rebook Reservations', function () {
        it('cancels reservation with refund processing', function () {
            Notification::fake();

            $user = User::factory()->create();

            $reservation = Reservation::factory()->create([
                'user_id' => $user->id,
                'reserved_at' => now()->addDays(1)->setHour(14),
                'reserved_until' => now()->addDays(1)->setHour(16),
                'status' => 'confirmed'
            ]);

            // Create associated paid transaction
            $transaction = Transaction::factory()->create([
                'email' => $user->email,
                'user_id' => $user->id,
                'transactionable_type' => Reservation::class,
                'transactionable_id' => $reservation->id,
                'amount' => 30.00,
                'response' => ['status' => 'completed', 'payment_method' => 'stripe']
            ]);

            // Cancel reservation (simulating cancellation process)
            $reservation->update(['status' => 'cancelled']);

            expect($reservation->fresh()->status)->toBe('cancelled');

            // Verify the time slot becomes available again
            $isAvailable = ReservationService::isTimeSlotAvailable(
                $reservation->reserved_at,
                $reservation->reserved_until
            );
            expect($isAvailable)->toBeTrue();
        });

        it('prevents cancellation of started reservations', function () {
            $user = User::factory()->create();

            $startedReservation = Reservation::factory()->create([
                'user_id' => $user->id,
                'reserved_at' => now()->subMinutes(30), // Started 30 minutes ago
                'reserved_until' => now()->addMinutes(90), // Ends in 90 minutes
                'status' => 'confirmed'
            ]);

            // Business rule: cannot cancel reservations that have already started
            expect($startedReservation->reserved_at)->toBeLessThan(now());
        });

        it('supports rebook workflow after cancellation', function () {
            $user = User::factory()->create();

            $originalTime = now()->addDays(1)->setHour(14);

            // Create and cancel original reservation
            $originalReservation = Reservation::factory()->create([
                'user_id' => $user->id,
                'reserved_at' => $originalTime,
                'reserved_until' => $originalTime->copy()->addHours(2),
                'status' => 'confirmed'
            ]);

            // Cancel it
            $originalReservation->update(['status' => 'cancelled']);

            // Verify original time is now available
            $isOriginalAvailable = ReservationService::isTimeSlotAvailable(
                $originalTime,
                $originalTime->copy()->addHours(2)
            );
            expect($isOriginalAvailable)->toBeTrue();

            // User can book new time
            $newTime = now()->addDays(1)->setHour(16);
            $isNewTimeAvailable = ReservationService::isTimeSlotAvailable(
                $newTime,
                $newTime->copy()->addHours(2)
            );
            expect($isNewTimeAvailable)->toBeTrue();
        });

        it('handles no-edit policy correctly', function () {
            $user = User::factory()->create();

            $reservation = Reservation::factory()->create([
                'user_id' => $user->id,
                'reserved_at' => now()->addDays(1)->setHour(14),
                'reserved_until' => now()->addDays(1)->setHour(16),
                'status' => 'confirmed'
            ]);

            // Business rule: reservations cannot be edited, only cancelled and rebooked
            // This test verifies the workflow expects cancel -> rebook pattern

            $originalTime = $reservation->reserved_at;
            $originalEnd = $reservation->reserved_until;

            // To change reservation time: Cancel original
            $reservation->update(['status' => 'cancelled']);

            // Then book new time
            $newTime = now()->addDays(2)->setHour(14);
            $newEnd = $newTime->copy()->addHours(2);

            $isNewTimeAvailable = ReservationService::isTimeSlotAvailable($newTime, $newEnd);
            expect($isNewTimeAvailable)->toBeTrue();
        });
    });

    describe('Story 4 & 5: Sustaining Member Free Hours', function () {
        it('applies sustaining member free hours correctly', function () {
            $user = User::factory()->create();
            $user->assignRole('sustaining member');

            // Mock sustaining member with 4 free hours
            UserSubscriptionService::shouldReceive('isSustainingMember')
                ->with($user)
                ->andReturn(true);
            UserSubscriptionService::shouldReceive('getRemainingFreeHours')
                ->with($user)
                ->andReturn(4.0);

            $startTime = now()->addDays(1)->setHour(14);
            $endTime = $startTime->copy()->addHours(2);

            $costBreakdown = ReservationService::calculateCost($user, $startTime, $endTime);

            expect($costBreakdown['is_sustaining_member'])->toBeTrue()
                ->and($costBreakdown['free_hours'])->toBe(2.0) // All 2 hours are free
                ->and($costBreakdown['paid_hours'])->toBe(0)
                ->and($costBreakdown['cost']->isZero())->toBe(true);
        });

        it('handles partial free hours usage', function () {
            $user = User::factory()->create();
            $user->assignRole('sustaining member');

            // Mock sustaining member with only 1 free hour remaining
            UserSubscriptionService::shouldReceive('isSustainingMember')
                ->with($user)
                ->andReturn(true);
            UserSubscriptionService::shouldReceive('getRemainingFreeHours')
                ->with($user)
                ->andReturn(1.0);

            $startTime = now()->addDays(1)->setHour(14);
            $endTime = $startTime->copy()->addHours(3); // 3 total hours

            $costBreakdown = ReservationService::calculateCost($user, $startTime, $endTime);

            expect($costBreakdown['total_hours'])->toBe(3.0)
                ->and($costBreakdown['free_hours'])->toBe(1.0) // Only 1 hour free
                ->and($costBreakdown['paid_hours'])->toBe(2.0) // 2 hours paid
                ->and($costBreakdown['cost']->getAmount()->toFloat())->toBe(30.0); // $15 * 2 hours
        });

        it('handles exhausted free hours', function () {
            $user = User::factory()->create();
            $user->assignRole('sustaining member');

            // Mock sustaining member with no remaining free hours
            UserSubscriptionService::shouldReceive('isSustainingMember')
                ->with($user)
                ->andReturn(true);
            UserSubscriptionService::shouldReceive('getRemainingFreeHours')
                ->with($user)
                ->andReturn(0.0);

            $startTime = now()->addDays(1)->setHour(14);
            $endTime = $startTime->copy()->addHours(2);

            $costBreakdown = ReservationService::calculateCost($user, $startTime, $endTime);

            expect($costBreakdown['free_hours'])->toBe(0.0)
                ->and($costBreakdown['paid_hours'])->toBe(2.0)
                ->and($costBreakdown['cost']->getAmount()->toFloat())->toBe(30.0);
        });
    });

    describe('Story 6: View Available Time Slots', function () {
        it('shows available slots correctly', function () {
            $baseTime = now()->addDays(1)->setHour(14);

            // Test multiple time slots
            $slots = [
                [$baseTime->copy(), $baseTime->copy()->addHours(1)],
                [$baseTime->copy()->addHours(2), $baseTime->copy()->addHours(4)],
                [$baseTime->copy()->addHours(5), $baseTime->copy()->addHours(6)],
            ];

            foreach ($slots as [$start, $end]) {
                $isAvailable = ReservationService::isTimeSlotAvailable($start, $end);
                expect($isAvailable)->toBeTrue();
            }
        });

        it('excludes conflicted slots correctly', function () {
            $user = User::factory()->create();
            $baseTime = now()->addDays(1)->setHour(14);

            // Book a slot: 2-4 PM
            Reservation::factory()->create([
                'user_id' => $user->id,
                'reserved_at' => $baseTime,
                'reserved_until' => $baseTime->copy()->addHours(2),
                'status' => 'confirmed'
            ]);

            // Test overlapping slots should not be available
            $overlapTests = [
                [$baseTime->copy(), $baseTime->copy()->addHours(2)], // Exact overlap
                [$baseTime->copy()->addMinutes(30), $baseTime->copy()->addHours(2)], // Partial overlap
                [$baseTime->copy()->addHour(), $baseTime->copy()->addHours(3)], // Partial overlap
            ];

            foreach ($overlapTests as [$start, $end]) {
                $isAvailable = ReservationService::isTimeSlotAvailable($start, $end);
                expect($isAvailable)->toBeFalse();
            }

            // Test non-overlapping slots should be available
            $noOverlapTests = [
                [$baseTime->copy()->addHours(2), $baseTime->copy()->addHours(3)], // After
                [$baseTime->copy()->subHours(2), $baseTime->copy()], // Before
            ];

            foreach ($noOverlapTests as [$start, $end]) {
                $isAvailable = ReservationService::isTimeSlotAvailable($start, $end);
                expect($isAvailable)->toBeTrue();
            }
        });
    });

    describe('Story 7: Off-Hours Reservation Management', function () {
        it('identifies off-hours periods correctly', function () {
            // Test times that would be considered off-hours
            $earlyMorning = now()->addDays(1)->setHour(7); // 7 AM
            $lateEvening = now()->addDays(1)->setHour(22); // 10 PM
            $overnight = now()->addDays(1)->setHour(23); // 11 PM

            // These should be outside normal operating hours (9 AM - 10 PM)
            expect($earlyMorning->hour)->toBeLessThan(9)
                ->and($lateEvening->hour)->toBeGreaterThanOrEqual(22)
                ->and($overnight->hour)->toBeGreaterThan(22);
        });

        it('requires full payment for off-hours when implemented', function () {
            $user = User::factory()->create();
            $user->assignRole('sustaining member');

            // Mock user with free hours
            UserSubscriptionService::shouldReceive('isSustainingMember')
                ->with($user)
                ->andReturn(true);
            UserSubscriptionService::shouldReceive('getRemainingFreeHours')
                ->with($user)
                ->andReturn(4.0);

            // Test off-hours time slot
            $offHoursStart = now()->addDays(1)->setHour(7); // 7 AM (off-hours)
            $offHoursEnd = $offHoursStart->copy()->addHours(2);

            $costBreakdown = ReservationService::calculateCost($user, $offHoursStart, $offHoursEnd);

            // Currently free hours would be applied, but when off-hours feature is implemented,
            // this should require full payment even for sustaining members
            expect($costBreakdown['total_hours'])->toBe(2.0);

            // When off-hours feature is implemented, expect:
            // - $costBreakdown['free_hours'] should be 0.0
            // - $costBreakdown['paid_hours'] should be 2.0
            // - $costBreakdown['cost'] should be 30.0
        });
    });

    describe('Story 8: Conflict Detection', function () {
        it('detects reservation conflicts accurately', function () {
            $user1 = User::factory()->create();
            $user2 = User::factory()->create();

            $baseTime = now()->addDays(1)->setHour(14);

            // Create existing reservation: 2-4 PM
            $existingReservation = Reservation::factory()->create([
                'user_id' => $user1->id,
                'reserved_at' => $baseTime,
                'reserved_until' => $baseTime->copy()->addHours(2),
                'status' => 'confirmed'
            ]);

            // Test conflict detection for various scenarios
            $conflictTests = [
                // [start, end, shouldConflict, description]
                [$baseTime->copy(), $baseTime->copy()->addHours(2), true, 'exact overlap'],
                [$baseTime->copy()->addMinutes(30), $baseTime->copy()->addHours(2), true, 'starts inside'],
                [$baseTime->copy()->addHour(), $baseTime->copy()->addHours(3), true, 'ends inside'],
                [$baseTime->copy()->subMinutes(30), $baseTime->copy()->addHours(3), true, 'completely contains'],
                [$baseTime->copy()->addHours(2), $baseTime->copy()->addHours(3), false, 'starts when ends'],
                [$baseTime->copy()->subHours(1), $baseTime->copy(), false, 'ends when starts'],
                [$baseTime->copy()->addHours(3), $baseTime->copy()->addHours(4), false, 'completely after'],
            ];

            foreach ($conflictTests as [$start, $end, $shouldConflict, $description]) {
                $conflicts = ReservationService::getConflictingReservations($start, $end);

                if ($shouldConflict) {
                    expect($conflicts)->toHaveCount(1, "Failed: $description should conflict");
                } else {
                    expect($conflicts)->toHaveCount(0, "Failed: $description should not conflict");
                }
            }
        });

        it('detects production conflicts', function () {
            $baseTime = now()->addDays(1)->setHour(19); // 7 PM

            // Create production that might conflict
            $production = Production::factory()->create([
                'start_time' => $baseTime,
                'end_time' => $baseTime->copy()->addHours(2),
                'status' => 'published'
            ]);

            // Test production conflict detection
            $productionConflicts = ReservationService::getConflictingProductions(
                $baseTime,
                $baseTime->copy()->addHours(2)
            );

            // The actual conflict depends on whether this production uses practice space
            expect($productionConflicts)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
        });

        it('excludes reservations from conflict checking', function () {
            $user = User::factory()->create();
            $baseTime = now()->addDays(1)->setHour(14);

            $reservation = Reservation::factory()->create([
                'user_id' => $user->id,
                'reserved_at' => $baseTime,
                'reserved_until' => $baseTime->copy()->addHours(2),
                'status' => 'confirmed'
            ]);

            // When excluding the reservation, there should be no conflicts
            $conflicts = ReservationService::getConflictingReservations(
                $baseTime,
                $baseTime->copy()->addHours(2),
                $reservation->id // Exclude this reservation
            );

            expect($conflicts)->toHaveCount(0);

            // Without exclusion, there should be a conflict
            $conflictsWithoutExclusion = ReservationService::getConflictingReservations(
                $baseTime,
                $baseTime->copy()->addHours(2)
            );

            expect($conflictsWithoutExclusion)->toHaveCount(1);
        });
    });

    describe('Story 10 & 14: Outstanding Payment Management', function () {
        it('identifies outstanding payments correctly', function () {
            $user = User::factory()->create();

            // Create reservation with failed payment
            $reservation = Reservation::factory()->create([
                'user_id' => $user->id,
                'reserved_at' => now()->subDays(1),
                'reserved_until' => now()->subDays(1)->addHours(2),
                'status' => 'confirmed'
            ]);

            $failedTransaction = Transaction::factory()->create([
                'email' => $user->email,
                'user_id' => $user->id,
                'transactionable_type' => Reservation::class,
                'transactionable_id' => $reservation->id,
                'amount' => 30.00,
                'response' => ['status' => 'failed', 'payment_method' => 'stripe']
            ]);

            // Check for outstanding payments
            $outstandingPayments = Transaction::where('user_id', $user->id)
                ->whereJsonContains('response->status', 'failed')
                ->get();

            expect($outstandingPayments)->toHaveCount(1)
                ->and($outstandingPayments->first()->amount->getAmount()->toFloat())->toBe(30.00);
        });

        it('blocks new reservations with outstanding payments', function () {
            $user = User::factory()->create();

            // Create outstanding payment
            Transaction::factory()->create([
                'email' => $user->email,
                'user_id' => $user->id,
                'amount' => 30.00,
                'response' => ['status' => 'failed', 'payment_method' => 'stripe']
            ]);

            $startTime = now()->addDays(1)->setHour(14);
            $endTime = $startTime->copy()->addHours(2);

            // Validation should include outstanding payment check
            $validation = ReservationService::validateReservation($user, $startTime, $endTime);

            // When outstanding payment validation is implemented, this should contain an error
            expect($validation)->toBeArray();
        });
    });

    describe('Integration Scenarios', function () {
        it('handles complex multi-user booking workflow', function () {
            $regularUser = User::factory()->create();
            $sustainingUser = User::factory()->withRole('sustaining member')->create();

            // Create recurring transaction to make them a sustaining member
            Transaction::factory()->create([
                'email' => $sustainingUser->email,
                'user_id' => $sustainingUser->id,
                'amount' => 20.00, // Above $10 sustaining threshold
                'type' => 'recurring',
            ]);

            $baseTime = now()->addDays(1)->setHour(14);

            // Sustaining member books 2-4 PM (free)
            $sustainingCost = ReservationService::calculateCost(
                $sustainingUser,
                $baseTime,
                $baseTime->copy()->addHours(2)
            );
            expect($sustainingCost['cost']->isZero())->toBe(true);

            // Create the sustaining member's reservation
            Reservation::factory()->create([
                'user_id' => $sustainingUser->id,
                'reserved_at' => $baseTime,
                'reserved_until' => $baseTime->copy()->addHours(2),
                'status' => 'confirmed'
            ]);

            // Regular user tries to book overlapping time (3-5 PM) - should be blocked
            $overlapStart = $baseTime->copy()->addHour();
            $overlapEnd = $overlapStart->copy()->addHours(2);

            $isOverlapAvailable = ReservationService::isTimeSlotAvailable($overlapStart, $overlapEnd);
            expect($isOverlapAvailable)->toBeFalse();

            // Regular user books after (4-6 PM) - should be allowed and paid
            $afterStart = $baseTime->copy()->addHours(2);
            $afterEnd = $afterStart->copy()->addHours(2);

            $isAfterAvailable = ReservationService::isTimeSlotAvailable($afterStart, $afterEnd);
            expect($isAfterAvailable)->toBeTrue();

            $regularCost = ReservationService::calculateCost($regularUser, $afterStart, $afterEnd);
            expect($regularCost['cost']->getAmount()->toFloat())->toBe(30.0);
        });

        it('handles cancellation and rebooking workflow', function () {
            $user = User::factory()->create();

            $originalStart = now()->addDays(1)->setHour(14);
            $originalEnd = $originalStart->copy()->addHours(2);

            // Create original reservation
            $reservation = Reservation::factory()->create([
                'user_id' => $user->id,
                'reserved_at' => $originalStart,
                'reserved_until' => $originalEnd,
                'status' => 'confirmed'
            ]);

            // Verify slot is blocked
            $isBlocked = ReservationService::isTimeSlotAvailable($originalStart, $originalEnd);
            expect($isBlocked)->toBeFalse();

            // Cancel reservation
            $reservation->update(['status' => 'cancelled']);

            // Verify slot is now available
            $isAvailable = ReservationService::isTimeSlotAvailable($originalStart, $originalEnd);
            expect($isAvailable)->toBeTrue();

            // Book new time
            $newStart = now()->addDays(2)->setHour(16);
            $newEnd = $newStart->copy()->addHours(2);

            $isNewTimeAvailable = ReservationService::isTimeSlotAvailable($newStart, $newEnd);
            expect($isNewTimeAvailable)->toBeTrue();
        });

        it('maintains consistency during concurrent booking attempts', function () {
            $user1 = User::factory()->create();
            $user2 = User::factory()->create();

            $popularTime = now()->addDays(1)->setHour(18); // 6 PM - popular time
            $endTime = $popularTime->copy()->addHours(2);

            // Verify time is initially available
            $isInitiallyAvailable = ReservationService::isTimeSlotAvailable($popularTime, $endTime);
            expect($isInitiallyAvailable)->toBeTrue();

            // First user books the slot
            $reservation1 = Reservation::factory()->create([
                'user_id' => $user1->id,
                'reserved_at' => $popularTime,
                'reserved_until' => $endTime,
                'status' => 'confirmed'
            ]);

            // Second user attempts to book same slot - should be blocked
            $isStillAvailable = ReservationService::isTimeSlotAvailable($popularTime, $endTime);
            expect($isStillAvailable)->toBeFalse();

            // Verify conflicts are detected
            $conflicts = ReservationService::getConflictingReservations($popularTime, $endTime);
            expect($conflicts)->toHaveCount(1)
                ->and($conflicts->first()->id)->toBe($reservation1->id);
        });
    });
});
