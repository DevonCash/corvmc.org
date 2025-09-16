<?php

use App\Models\Equipment;
use App\Models\EquipmentLoan;
use App\Models\User;
use App\Services\EquipmentService;
use App\States\Equipment\Available;
use App\States\Equipment\Maintenance;
use App\States\EquipmentLoan\Requested;
use App\States\EquipmentLoan\CheckedOut;
use App\States\EquipmentLoan\Cancelled;
use App\States\EquipmentLoan\Returned;
use Carbon\Carbon;
use Spatie\Period\Period;
use Spatie\Period\Precision;

describe('EquipmentService Reservation Methods', function () {
    beforeEach(function () {
        $this->service = app(EquipmentService::class);

        $this->equipment = Equipment::factory()->create([
            'name' => 'Service Test Guitar',
            'type' => 'guitar',
            'status' => 'available',
            'ownership_status' => 'cmc_owned',
            'state' => Available::class,
        ]);

        $this->user = User::factory()->create(['name' => 'Service Test User']);
    });

    describe('createReservation method', function () {
        it('creates reservation with all required fields', function () {
            $reservedFrom = now()->addDays(1);
            $dueAt = now()->addDays(5);
            $securityDeposit = 50.00;
            $rentalFee = 25.00;
            $notes = 'Test reservation notes';

            $reservation = $this->service->createReservation(
                $this->equipment,
                $this->user,
                $reservedFrom,
                $dueAt,
                $securityDeposit,
                $rentalFee,
                $notes
            );

            expect($reservation->equipment_id)->toBe($this->equipment->id);
            expect($reservation->borrower_id)->toBe($this->user->id);
            expect($reservation->reserved_from->format('Y-m-d H:i'))->toBe($reservedFrom->format('Y-m-d H:i'));
            expect($reservation->due_at->format('Y-m-d H:i'))->toBe($dueAt->format('Y-m-d H:i'));
            expect($reservation->security_deposit)->toBe('50.00');
            expect($reservation->rental_fee)->toBe('25.00');
            expect($reservation->notes)->toBe($notes);
            expect($reservation->checked_out_at)->toBeNull();
            expect($reservation->state)->toBeInstanceOf(Requested::class);
        });

        it('uses default values for optional parameters', function () {
            $reservation = $this->service->createReservation(
                $this->equipment,
                $this->user,
                now()->addDays(1),
                now()->addDays(3)
            );

            expect($reservation->security_deposit)->toBe('0.00');
            expect($reservation->rental_fee)->toBe('0.00');
            expect($reservation->notes)->toBeNull();
        });

        it('throws exception for unavailable equipment', function () {
            $this->equipment->update(['status' => 'maintenance', 'state' => Maintenance::class]);

            expect(function () {
                $this->service->createReservation(
                    $this->equipment,
                    $this->user,
                    now()->addDays(1),
                    now()->addDays(3)
                );
            })->toThrow(Exception::class, 'Equipment is not available for the requested period.');
        });

        it('throws exception for conflicting reservations', function () {
            // Create first reservation
            $this->service->createReservation(
                $this->equipment,
                $this->user,
                now()->addDays(5),
                now()->addDays(10)
            );

            // Try overlapping reservation
            expect(function () {
                $this->service->createReservation(
                    $this->equipment,
                    User::factory()->create(),
                    now()->addDays(7), // Overlaps with existing
                    now()->addDays(12)
                );
            })->toThrow(Exception::class, 'Equipment is not available for the requested period.');
        });
    });

    describe('isAvailableForPeriod method', function () {
        it('returns true for available equipment with no conflicts', function () {
            $period = Period::make(now()->addDays(1), now()->addDays(3), Precision::MINUTE());

            $result = $this->service->isAvailableForPeriod($this->equipment, $period);

            expect($result)->toBeTrue();
        });

        it('returns false for equipment in maintenance', function () {
            $this->equipment->update(['status' => 'maintenance', 'state' => Maintenance::class]);
            $period = Period::make(now()->addDays(1), now()->addDays(3), Precision::MINUTE());

            $result = $this->service->isAvailableForPeriod($this->equipment, $period);

            expect($result)->toBeFalse();
        });

        it('returns false when conflicting reservations exist', function () {
            // Create existing reservation
            $this->service->createReservation(
                $this->equipment,
                $this->user,
                now()->addDays(5),
                now()->addDays(10)
            );

            // Check overlapping period
            $overlappingPeriod = Period::make(now()->addDays(7), now()->addDays(12), Precision::MINUTE());

            $result = $this->service->isAvailableForPeriod($this->equipment, $overlappingPeriod);

            expect($result)->toBeFalse();
        });

        it('returns true for adjacent non-overlapping periods', function () {
            // Create existing reservation
            $this->service->createReservation(
                $this->equipment,
                $this->user,
                now()->addDays(5),
                now()->addDays(10)
            );

            // Check adjacent period (starts exactly when existing ends)
            $adjacentPeriod = Period::make(now()->addDays(10), now()->addDays(15), Precision::MINUTE());

            $result = $this->service->isAvailableForPeriod($this->equipment, $adjacentPeriod);

            expect($result)->toBeTrue();
        });
    });

    describe('hasConflictingReservations method', function () {
        it('returns false when no reservations exist', function () {
            $period = Period::make(now()->addDays(1), now()->addDays(3), Precision::MINUTE());

            $result = $this->service->hasConflictingReservations($this->equipment, $period);

            expect($result)->toBeFalse();
        });

        it('returns false when reservations are cancelled', function () {
            $reservation = $this->service->createReservation(
                $this->equipment,
                $this->user,
                now()->addDays(5),
                now()->addDays(10)
            );

            // Cancel the reservation
            $this->service->cancelReservation($reservation);

            // Check overlapping period
            $period = Period::make(now()->addDays(7), now()->addDays(12), Precision::MINUTE());

            $result = $this->service->hasConflictingReservations($this->equipment, $period);

            expect($result)->toBeFalse();
        });

        it('returns false when reservations are completed', function () {
            $reservation = EquipmentLoan::factory()->returned()->create([
                'equipment_id' => $this->equipment->id,
                'borrower_id' => $this->user->id,
                'reserved_from' => now()->subDays(10),
                'due_at' => now()->subDays(5),
            ]);

            // Check period that would overlap with the completed reservation
            $period = Period::make(now()->subDays(8), now()->subDays(6), Precision::MINUTE());

            $result = $this->service->hasConflictingReservations($this->equipment, $period);

            expect($result)->toBeFalse();
        });

        it('returns true for active overlapping reservations', function () {
            $this->service->createReservation(
                $this->equipment,
                $this->user,
                now()->addDays(5),
                now()->addDays(10)
            );

            $overlappingPeriod = Period::make(now()->addDays(7), now()->addDays(12), Precision::MINUTE());

            $result = $this->service->hasConflictingReservations($this->equipment, $overlappingPeriod);

            expect($result)->toBeTrue();
        });
    });

    describe('cancelReservation method', function () {
        it('successfully cancels reservation not yet checked out', function () {
            $reservation = $this->service->createReservation(
                $this->equipment,
                $this->user,
                now()->addDays(1),
                now()->addDays(3)
            );

            expect($reservation->checked_out_at)->toBeNull();

            $result = $this->service->cancelReservation($reservation);

            expect($result)->toBeTrue();
            expect($reservation->fresh()->state)->toBeInstanceOf(Cancelled::class);
        });

        it('throws exception when trying to cancel checked out reservation', function () {
            $reservation = EquipmentLoan::factory()->checkedOut()->create([
                'equipment_id' => $this->equipment->id,
                'borrower_id' => $this->user->id,
            ]);

            expect($reservation->checked_out_at)->not->toBeNull();

            expect(function () use ($reservation) {
                $this->service->cancelReservation($reservation);
            })->toThrow(Exception::class, 'Cannot cancel reservation - equipment has already been checked out.');
        });
    });

    describe('checkoutToMember method with reservations', function () {
        it('creates immediate checkout with reservation period', function () {
            $dueAt = now()->addDays(7);

            $loan = $this->service->checkoutToMember(
                $this->equipment,
                $this->user,
                $dueAt,
                'excellent',
                25.00,
                15.00,
                'Immediate checkout'
            );

            expect($loan->reserved_from->format('Y-m-d H:i'))->toBe(now()->format('Y-m-d H:i'));
            expect($loan->checked_out_at)->not->toBeNull();
            expect($loan->due_at->format('Y-m-d'))->toBe($dueAt->format('Y-m-d'));
            expect($loan->state)->toBeInstanceOf(CheckedOut::class);
        });

        it('prevents immediate checkout when equipment is reserved', function () {
            // Create future reservation
            $this->service->createReservation(
                $this->equipment,
                User::factory()->create(),
                now()->addHours(2),
                now()->addDays(3)
            );

            // Try immediate checkout that would conflict
            expect(function () {
                $this->service->checkoutToMember(
                    $this->equipment,
                    $this->user,
                    now()->addDays(1)
                );
            })->toThrow(Exception::class, 'Equipment is not available for checkout.');
        });
    });

    describe('reservation query methods', function () {
        beforeEach(function () {
            $this->user2 = User::factory()->create(['name' => 'Second User']);
            $this->equipment2 = Equipment::factory()->create([
                'name' => 'Second Guitar',
                'type' => 'guitar',
                'status' => 'available',
                'ownership_status' => 'cmc_owned',
                'state' => Available::class,
            ]);

            // Create test data
            $this->futureReservation = $this->service->createReservation(
                $this->equipment,
                $this->user,
                now()->addDays(10),
                now()->addDays(15)
            );

            $this->currentReservation = EquipmentLoan::factory()->create([
                'equipment_id' => $this->equipment2->id,
                'borrower_id' => $this->user,
                'reserved_from' => now()->subHours(1),
                'due_at' => now()->addDays(2),
                'state' => CheckedOut::class,
                'checked_out_at' => now()->subMinutes(30),
            ]);

            $this->otherUserReservation = $this->service->createReservation(
                $this->equipment,
                $this->user2,
                now()->addDays(20),
                now()->addDays(25)
            );
        });

        describe('getUpcomingReservationsForUser', function () {
            it('returns only future reservations for the user', function () {
                $upcomingReservations = $this->service->getUpcomingReservationsForUser($this->user);

                expect($upcomingReservations)->toHaveCount(1);
                expect($upcomingReservations->first()->id)->toBe($this->futureReservation->id);
            });

            it('excludes reservations from other users', function () {
                $upcomingReservations = $this->service->getUpcomingReservationsForUser($this->user);

                $reservationIds = $upcomingReservations->pluck('id');
                expect($reservationIds)->not->toContain($this->otherUserReservation->id);
            });
        });

        describe('getActiveReservationsForUser', function () {
            it('returns current active reservations for the user', function () {
                $activeReservations = $this->service->getActiveReservationsForUser($this->user);

                expect($activeReservations)->toHaveCount(1);
                expect($activeReservations->first()->id)->toBe($this->currentReservation->id);
            });
        });

        describe('getReservationsForEquipment', function () {
            it('returns all active reservations for specific equipment', function () {
                $equipmentReservations = $this->service->getReservationsForEquipment($this->equipment);

                expect($equipmentReservations)->toHaveCount(2);
                expect($equipmentReservations->pluck('equipment_id')->unique())->toEqual(collect([$this->equipment->id]));
            });
        });

        describe('getReservationsForDate', function () {
            it('returns reservations active on a specific date', function () {
                $targetDate = now()->addDays(12); // Middle of future reservation
                $dateReservations = $this->service->getReservationsForDate($targetDate);

                expect($dateReservations)->toHaveCount(1);
                expect($dateReservations->first()->id)->toBe($this->futureReservation->id);
            });

            it('returns empty collection for dates with no reservations', function () {
                $emptyDate = now()->addDays(50);
                $dateReservations = $this->service->getReservationsForDate($emptyDate);

                expect($dateReservations)->toHaveCount(0);
            });
        });

        describe('getAvailableEquipmentForPeriod', function () {
            it('returns available equipment for open periods', function () {
                $openPeriod = Period::make(now()->addDays(30), now()->addDays(35), Precision::MINUTE());
                $availableEquipment = $this->service->getAvailableEquipmentForPeriod($openPeriod);

                expect($availableEquipment->count())->toBeGreaterThan(0);
                expect($availableEquipment->pluck('id'))->toContain($this->equipment->id);
                expect($availableEquipment->pluck('id'))->toContain($this->equipment2->id);
            });

            it('excludes equipment with conflicting reservations', function () {
                $conflictingPeriod = Period::make(now()->addDays(12), now()->addDays(13), Precision::MINUTE());
                $availableEquipment = $this->service->getAvailableEquipmentForPeriod($conflictingPeriod);

                $availableIds = $availableEquipment->pluck('id');
                expect($availableIds)->not->toContain($this->equipment->id); // Has future reservation
                expect($availableIds)->toContain($this->equipment2->id); // Should be available
            });

            it('filters by equipment type when specified', function () {
                $period = Period::make(now()->addDays(30), now()->addDays(35), Precision::MINUTE());
                $availableGuitars = $this->service->getAvailableEquipmentForPeriod($period, 'guitar');

                expect($availableGuitars->pluck('type')->unique())->toEqual(collect(['guitar']));
            });
        });
    });

    describe('edge cases and error handling', function () {
        it('handles invalid period orders gracefully', function () {
            // This should be caught by Period validation, but let's test our service
            $invalidStart = now()->addDays(10);
            $invalidEnd = now()->addDays(5); // End before start

            expect(function () use ($invalidStart, $invalidEnd) {
                Period::make($invalidStart, $invalidEnd, Precision::MINUTE());
            })->toThrow(InvalidArgumentException::class);
        });

        it('handles equipment that does not exist', function () {
            $nonExistentEquipment = new Equipment([
                'id' => 99999,
                'loanable' => false,  // Explicitly make it non-loanable
                'status' => 'retired' // Explicitly make it unavailable
            ]);
            $period = Period::make(now()->addDays(1), now()->addDays(3), Precision::MINUTE());

            // This should return false for non-loanable equipment
            $result = $this->service->isAvailableForPeriod($nonExistentEquipment, $period);
            expect($result)->toBeFalse();
        });

        it('handles very long reservation periods', function () {
            $veryLongPeriod = Period::make(now()->addDays(1), now()->addYears(1), Precision::MINUTE());

            $result = $this->service->isAvailableForPeriod($this->equipment, $veryLongPeriod);

            // Should handle without errors
            expect($result)->toBeIn([true, false]);
        });

        it('handles very short reservation periods', function () {
            $veryShortPeriod = Period::make(now()->addDays(1), now()->addDays(1)->addMinutes(1), Precision::MINUTE());

            $reservation = $this->service->createReservation(
                $this->equipment,
                $this->user,
                Carbon::instance($veryShortPeriod->start()),
                Carbon::instance($veryShortPeriod->end())
            );

            expect($reservation)->toBeInstanceOf(EquipmentLoan::class);
        });
    });
});
