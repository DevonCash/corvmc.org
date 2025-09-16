<?php

use App\Models\Equipment;
use App\Models\EquipmentLoan;
use App\Models\User;
use App\Models\Production;
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

describe('Equipment Reservation System', function () {
    beforeEach(function () {
        $this->equipmentService = app(EquipmentService::class);
        
        $this->guitar = Equipment::factory()->create([
            'name' => 'Test Guitar',
            'type' => 'guitar',
            'status' => 'available',
            'ownership_status' => 'cmc_owned',
            'state' => Available::class,
        ]);
        
        $this->amp = Equipment::factory()->create([
            'name' => 'Test Amplifier',
            'type' => 'amplifier',
            'status' => 'available',
            'ownership_status' => 'cmc_owned',
            'state' => Available::class,
        ]);
        
        $this->member1 = User::factory()->create(['name' => 'Member One']);
        $this->member2 = User::factory()->create(['name' => 'Member Two']);
        $this->staff = User::factory()->create(['name' => 'Staff Member']);
    });

    describe('Story 16: Reserve Equipment for Future Events', function () {
        it('can create a future reservation', function () {
            $reservedFrom = now()->addDays(3);
            $dueAt = now()->addDays(10);
            
            $reservation = $this->equipmentService->createReservation(
                $this->guitar,
                $this->member1,
                $reservedFrom,
                $dueAt,
                25.00, // security deposit
                15.00, // rental fee
                'Recording session reservation'
            );

            expect($reservation)->toBeInstanceOf(EquipmentLoan::class);
            expect($reservation->equipment_id)->toBe($this->guitar->id);
            expect($reservation->borrower_id)->toBe($this->member1->id);
            expect($reservation->reserved_from->format('Y-m-d'))->toBe($reservedFrom->format('Y-m-d'));
            expect($reservation->due_at->format('Y-m-d'))->toBe($dueAt->format('Y-m-d'));
            expect($reservation->checked_out_at)->toBeNull();
            expect($reservation->security_deposit)->toBe('25.00');
            expect($reservation->rental_fee)->toBe('15.00');
            expect($reservation->notes)->toBe('Recording session reservation');
            expect($reservation->state)->toBeInstanceOf(Requested::class);
        });

        it('can reserve multiple pieces of equipment for the same event', function () {
            $reservedFrom = now()->addDays(2);
            $dueAt = now()->addDays(9);
            
            $guitarReservation = $this->equipmentService->createReservation(
                $this->guitar,
                $this->member1,
                $reservedFrom,
                $dueAt
            );
            
            $ampReservation = $this->equipmentService->createReservation(
                $this->amp,
                $this->member1,
                $reservedFrom,
                $dueAt
            );

            expect($guitarReservation->borrower_id)->toBe($this->member1->id);
            expect($ampReservation->borrower_id)->toBe($this->member1->id);
            expect($guitarReservation->reserved_from->format('Y-m-d H:i'))
                ->toBe($ampReservation->reserved_from->format('Y-m-d H:i'));
            expect($guitarReservation->due_at->format('Y-m-d H:i'))
                ->toBe($ampReservation->due_at->format('Y-m-d H:i'));
        });

        it('provides access to reservation period information', function () {
            $reservedFrom = now()->addDays(1);
            $dueAt = now()->addDays(8);
            
            $reservation = $this->equipmentService->createReservation(
                $this->guitar,
                $this->member1,
                $reservedFrom,
                $dueAt
            );

            $period = $reservation->getReservationPeriod();
            expect($period)->toBeInstanceOf(Period::class);
            expect($period->start()->format('Y-m-d H:i'))->toBe($reservedFrom->format('Y-m-d H:i'));
            expect($period->end()->format('Y-m-d H:i'))->toBe($dueAt->format('Y-m-d H:i'));
        });

        it('tracks reservation status correctly', function () {
            // Future reservation
            $futureReservation = EquipmentLoan::factory()->futureReservation()->create([
                'equipment_id' => $this->guitar->id,
                'borrower_id' => $this->member1->id,
            ]);
            
            expect($futureReservation->is_reservation_upcoming)->toBeTrue();
            expect($futureReservation->is_reservation_active)->toBeFalse();
            expect($futureReservation->is_reservation_expired)->toBeFalse();

            // Current reservation
            $currentReservation = EquipmentLoan::factory()->currentReservation()->create([
                'equipment_id' => $this->amp->id,
                'borrower_id' => $this->member1->id,
            ]);
            
            expect($currentReservation->is_reservation_upcoming)->toBeFalse();
            expect($currentReservation->is_reservation_active)->toBeTrue();
            expect($currentReservation->is_reservation_expired)->toBeFalse();
        });
    });

    describe('Story 18: Automatic Conflict Detection', function () {
        it('prevents double-booking of equipment', function () {
            $reservedFrom = now()->addDays(5);
            $dueAt = now()->addDays(12);
            
            // Create first reservation
            $firstReservation = $this->equipmentService->createReservation(
                $this->guitar,
                $this->member1,
                $reservedFrom,
                $dueAt
            );

            expect($firstReservation)->toBeInstanceOf(EquipmentLoan::class);

            // Try to create conflicting reservation
            $conflictingStart = now()->addDays(7); // Overlaps with first reservation
            $conflictingEnd = now()->addDays(14);
            
            expect(function () use ($conflictingStart, $conflictingEnd) {
                $this->equipmentService->createReservation(
                    $this->guitar,
                    $this->member2,
                    $conflictingStart,
                    $conflictingEnd
                );
            })->toThrow(Exception::class, 'Equipment is not available for the requested period.');
        });

        it('detects partial period overlaps', function () {
            $reservedFrom = now()->addDays(10);
            $dueAt = now()->addDays(15);
            
            $firstReservation = $this->equipmentService->createReservation(
                $this->guitar,
                $this->member1,
                $reservedFrom,
                $dueAt
            );

            // Test start overlap
            expect(function () {
                $this->equipmentService->createReservation(
                    $this->guitar,
                    $this->member2,
                    now()->addDays(8), // Starts before, ends during
                    now()->addDays(12)
                );
            })->toThrow(Exception::class);

            // Test end overlap
            expect(function () {
                $this->equipmentService->createReservation(
                    $this->guitar,
                    $this->member2,
                    now()->addDays(13), // Starts during, ends after
                    now()->addDays(18)
                );
            })->toThrow(Exception::class);

            // Test complete overlap
            expect(function () {
                $this->equipmentService->createReservation(
                    $this->guitar,
                    $this->member2,
                    now()->addDays(8), // Completely contains existing reservation
                    now()->addDays(18)
                );
            })->toThrow(Exception::class);
        });

        it('allows adjacent reservations without conflict', function () {
            $firstStart = now()->addDays(5);
            $firstEnd = now()->addDays(10);
            
            $firstReservation = $this->equipmentService->createReservation(
                $this->guitar,
                $this->member1,
                $firstStart,
                $firstEnd
            );

            // Adjacent reservation starting exactly when first one ends
            $secondReservation = $this->equipmentService->createReservation(
                $this->guitar,
                $this->member2,
                $firstEnd, // Starts exactly when first ends
                now()->addDays(15)
            );

            expect($firstReservation)->toBeInstanceOf(EquipmentLoan::class);
            expect($secondReservation)->toBeInstanceOf(EquipmentLoan::class);
            expect($firstReservation->due_at->format('Y-m-d H:i:s'))
                ->toBe($secondReservation->reserved_from->format('Y-m-d H:i:s'));
        });

        it('checks availability for specific periods', function () {
            $reservedFrom = now()->addDays(7);
            $dueAt = now()->addDays(12);
            
            $period = Period::make($reservedFrom, $dueAt, Precision::MINUTE());
            
            // Initially available
            expect($this->equipmentService->isAvailableForPeriod($this->guitar, $period))->toBeTrue();
            
            // Create reservation
            $this->equipmentService->createReservation(
                $this->guitar,
                $this->member1,
                $reservedFrom,
                $dueAt
            );
            
            // Now unavailable for same period
            expect($this->equipmentService->isAvailableForPeriod($this->guitar, $period))->toBeFalse();
            
            // But available for different period
            $differentPeriod = Period::make(now()->addDays(15), now()->addDays(20), Precision::MINUTE());
            expect($this->equipmentService->isAvailableForPeriod($this->guitar, $differentPeriod))->toBeTrue();
        });

        it('respects equipment status in availability checks', function () {
            $period = Period::make(now()->addDays(1), now()->addDays(5), Precision::MINUTE());
            
            // Available equipment
            expect($this->equipmentService->isAvailableForPeriod($this->guitar, $period))->toBeTrue();
            
            // Equipment in maintenance
            $this->guitar->update(['status' => 'maintenance', 'state' => Maintenance::class]);
            expect($this->equipmentService->isAvailableForPeriod($this->guitar, $period))->toBeFalse();
        });
    });

    describe('Story 19: Reservation Modifications', function () {
        it('can cancel reservations before checkout', function () {
            $reservation = EquipmentLoan::factory()->futureReservation()->create([
                'equipment_id' => $this->guitar->id,
                'borrower_id' => $this->member1->id,
            ]);

            expect($reservation->checked_out_at)->toBeNull();
            
            $result = $this->equipmentService->cancelReservation($reservation);
            
            expect($result)->toBeTrue();
            expect($reservation->fresh()->state)->toBeInstanceOf(Cancelled::class);
        });

        it('prevents cancellation after checkout', function () {
            $reservation = EquipmentLoan::factory()->checkedOut()->create([
                'equipment_id' => $this->guitar->id,
                'borrower_id' => $this->member1->id,
            ]);

            expect($reservation->checked_out_at)->not->toBeNull();
            
            expect(function () use ($reservation) {
                $this->equipmentService->cancelReservation($reservation);
            })->toThrow(Exception::class, 'Cannot cancel reservation - equipment has already been checked out.');
        });
    });

    describe('Reservation Query Methods', function () {
        beforeEach(function () {
            // Create test reservations
            $this->futureReservation = EquipmentLoan::factory()->futureReservation()->create([
                'equipment_id' => $this->guitar->id,
                'borrower_id' => $this->member1->id,
                'reserved_from' => now()->addDays(5),
                'due_at' => now()->addDays(10),
            ]);
            
            $this->currentReservation = EquipmentLoan::factory()->currentReservation()->create([
                'equipment_id' => $this->amp->id,
                'borrower_id' => $this->member1->id,
                'reserved_from' => now()->subHours(2),
                'due_at' => now()->addDays(3),
            ]);
            
            $this->otherMemberReservation = EquipmentLoan::factory()->futureReservation()->create([
                'equipment_id' => $this->guitar->id,
                'borrower_id' => $this->member2->id,
                'reserved_from' => now()->addDays(15),
                'due_at' => now()->addDays(20),
            ]);
        });

        it('gets upcoming reservations for a user', function () {
            $upcomingReservations = $this->equipmentService->getUpcomingReservationsForUser($this->member1);
            
            expect($upcomingReservations)->toHaveCount(1);
            expect($upcomingReservations->first()->id)->toBe($this->futureReservation->id);
        });

        it('gets active reservations for a user', function () {
            $activeReservations = $this->equipmentService->getActiveReservationsForUser($this->member1);
            
            expect($activeReservations)->toHaveCount(1);
            expect($activeReservations->first()->id)->toBe($this->currentReservation->id);
        });

        it('gets reservations for specific equipment', function () {
            $guitarReservations = $this->equipmentService->getReservationsForEquipment($this->guitar);
            
            expect($guitarReservations)->toHaveCount(2);
            expect($guitarReservations->pluck('borrower_id'))->toContain($this->member1->id);
            expect($guitarReservations->pluck('borrower_id'))->toContain($this->member2->id);
        });

        it('gets reservations for a specific date', function () {
            $targetDate = now()->addDays(5);
            $dateReservations = $this->equipmentService->getReservationsForDate($targetDate);
            
            expect($dateReservations)->toHaveCount(1);
            expect($dateReservations->first()->id)->toBe($this->futureReservation->id);
        });

        it('finds available equipment for a period', function () {
            $period = Period::make(now()->addDays(25), now()->addDays(30), Precision::MINUTE());
            $availableEquipment = $this->equipmentService->getAvailableEquipmentForPeriod($period);
            
            // Both guitar and amp should be available for this future period
            expect($availableEquipment)->toHaveCount(2);
            expect($availableEquipment->pluck('id'))->toContain($this->guitar->id);
            expect($availableEquipment->pluck('id'))->toContain($this->amp->id);
        });

        it('filters available equipment by type', function () {
            $period = Period::make(now()->addDays(25), now()->addDays(30), Precision::MINUTE());
            $availableGuitars = $this->equipmentService->getAvailableEquipmentForPeriod($period, 'guitar');
            
            expect($availableGuitars)->toHaveCount(1);
            expect($availableGuitars->first()->id)->toBe($this->guitar->id);
            expect($availableGuitars->first()->type)->toBe('guitar');
        });
    });

    describe('Reservation Period Scopes', function () {
        beforeEach(function () {
            // Create reservations with known periods
            $this->activeReservation = EquipmentLoan::factory()->create([
                'equipment_id' => $this->guitar->id,
                'borrower_id' => $this->member1->id,
                'reserved_from' => now()->subDays(1),
                'due_at' => now()->addDays(2),
                'state' => Requested::class,
            ]);
            
            $this->futureReservation = EquipmentLoan::factory()->create([
                'equipment_id' => $this->amp->id,
                'borrower_id' => $this->member2->id,
                'reserved_from' => now()->addDays(5),
                'due_at' => now()->addDays(8),
                'state' => Requested::class,
            ]);
            
            $this->pastReservation = EquipmentLoan::factory()->create([
                'equipment_id' => $this->guitar->id,
                'borrower_id' => $this->member1->id,
                'reserved_from' => now()->subDays(10),
                'due_at' => now()->subDays(5),
                'state' => Returned::class,
            ]);
        });

        it('finds reservations with active periods', function () {
            $activeReservations = EquipmentLoan::withActiveReservations()->get();
            
            expect($activeReservations)->toHaveCount(1);
            expect($activeReservations->first()->id)->toBe($this->activeReservation->id);
        });

        it('finds upcoming reservations', function () {
            $upcomingReservations = EquipmentLoan::upcomingReservations()->get();
            
            expect($upcomingReservations)->toHaveCount(1);
            expect($upcomingReservations->first()->id)->toBe($this->futureReservation->id);
        });

        it('finds reservations overlapping with a period', function () {
            $testPeriod = Period::make(now(), now()->addDays(1), Precision::MINUTE());
            $overlappingReservations = EquipmentLoan::overlappingPeriod($testPeriod)->get();
            
            expect($overlappingReservations)->toHaveCount(1);
            expect($overlappingReservations->first()->id)->toBe($this->activeReservation->id);
        });

        it('finds reservations on a specific date', function () {
            $targetDate = now()->addDays(6); // Middle of future reservation
            $dateReservations = EquipmentLoan::onDate($targetDate)->get();
            
            expect($dateReservations)->toHaveCount(1);
            expect($dateReservations->first()->id)->toBe($this->futureReservation->id);
        });
    });

    describe('Integration with Existing Workflow', function () {
        it('maintains state machine compatibility', function () {
            $reservation = $this->equipmentService->createReservation(
                $this->guitar,
                $this->member1,
                now()->addDays(1),
                now()->addDays(5)
            );

            // Should start in Requested state
            expect($reservation->state)->toBeInstanceOf(Requested::class);
            
            // Should support existing state transitions
            expect($reservation->state->canTransitionTo(\App\States\EquipmentLoan\StaffPreparing::class))->toBeTrue();
            expect($reservation->state->canTransitionTo(Cancelled::class))->toBeTrue();
        });

        it('supports immediate checkout workflow', function () {
            $immediateCheckout = $this->equipmentService->checkoutToMember(
                $this->guitar,
                $this->member1,
                now()->addDays(7),
                'good',
                25.00,
                15.00,
                'Immediate checkout'
            );

            expect($immediateCheckout->reserved_from->format('Y-m-d H:i'))->toBe(now()->format('Y-m-d H:i'));
            expect($immediateCheckout->checked_out_at)->not->toBeNull();
            expect($immediateCheckout->state)->toBeInstanceOf(CheckedOut::class);
        });

        it('prevents immediate checkout when equipment is reserved', function () {
            // Create future reservation
            $this->equipmentService->createReservation(
                $this->guitar,
                $this->member1,
                now()->addMinutes(30),
                now()->addDays(3)
            );

            // Try immediate checkout that would conflict
            expect(function () {
                $this->equipmentService->checkoutToMember(
                    $this->guitar,
                    $this->member2,
                    now()->addDays(1),
                    'good'
                );
            })->toThrow(Exception::class, 'Equipment is not available for checkout.');
        });
    });

    describe('Period Overlap Detection', function () {
        it('detects exact period matches', function () {
            $period1 = Period::make(now()->addDays(1), now()->addDays(5), Precision::MINUTE());
            $period2 = Period::make(now()->addDays(1), now()->addDays(5), Precision::MINUTE());
            
            $reservation = EquipmentLoan::factory()->create([
                'equipment_id' => $this->guitar->id,
                'borrower_id' => $this->member1->id,
                'reserved_from' => $period1->start(),
                'due_at' => $period1->end(),
                'state' => Requested::class,
            ]);

            expect($reservation->overlapsWithPeriod($period2))->toBeTrue();
        });

        it('detects partial overlaps correctly', function () {
            $basePeriod = Period::make(now()->addDays(5), now()->addDays(10), Precision::MINUTE());
            
            $reservation = EquipmentLoan::factory()->create([
                'equipment_id' => $this->guitar->id,
                'borrower_id' => $this->member1->id,
                'reserved_from' => $basePeriod->start(),
                'due_at' => $basePeriod->end(),
                'state' => Requested::class,
            ]);

            // Start overlap
            $startOverlap = Period::make(now()->addDays(3), now()->addDays(7), Precision::MINUTE());
            expect($reservation->overlapsWithPeriod($startOverlap))->toBeTrue();

            // End overlap
            $endOverlap = Period::make(now()->addDays(8), now()->addDays(12), Precision::MINUTE());
            expect($reservation->overlapsWithPeriod($endOverlap))->toBeTrue();

            // Contains
            $contains = Period::make(now()->addDays(2), now()->addDays(15), Precision::MINUTE());
            expect($reservation->overlapsWithPeriod($contains))->toBeTrue();

            // Contained within
            $contained = Period::make(now()->addDays(6), now()->addDays(8), Precision::MINUTE());
            expect($reservation->overlapsWithPeriod($contained))->toBeTrue();

            // No overlap
            $noOverlap = Period::make(now()->addDays(15), now()->addDays(20), Precision::MINUTE());
            expect($reservation->overlapsWithPeriod($noOverlap))->toBeFalse();
        });
    });
});